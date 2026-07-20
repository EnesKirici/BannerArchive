<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class VideoDownloaderService
{
    private const YOUTUBE_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtu.be',
    ];

    private const INSTAGRAM_HOSTS = [
        'instagram.com',
        'www.instagram.com',
        'm.instagram.com',
    ];

    private const YOUTUBE_PATH_PREFIXES = ['/watch', '/shorts/', '/live/', '/clip/'];

    private const INSTAGRAM_PATH_PREFIXES = ['/reel/', '/reels/', '/p/', '/tv/'];

    /**
     * Format anahtarı → yt-dlp format seçici.
     *
     * H.264 (avc1) + AAC (m4a) öncelikli: Twitter/X gibi platformlar AV1, VP9
     * ve HEVC codec'lerini kabul etmediği için önce avc1 denenir.
     *
     * @var array<string, string>
     */
    private const FORMAT_MAP = [
        'mp4_best' => 'bv*[ext=mp4][vcodec^=avc1]+ba[ext=m4a]/bv*[vcodec^=avc1]+ba[ext=m4a]/b[ext=mp4][vcodec^=avc1]/bv*[ext=mp4]+ba[ext=m4a]/b[ext=mp4]/bv*+ba/b',
        'mp4_720' => 'bv*[ext=mp4][vcodec^=avc1][height<=720]+ba[ext=m4a]/bv*[vcodec^=avc1][height<=720]+ba[ext=m4a]/b[ext=mp4][vcodec^=avc1][height<=720]/bv*[ext=mp4][height<=720]+ba[ext=m4a]/b[ext=mp4][height<=720]/bv*[height<=720]+ba/b',
        'mp4_480' => 'bv*[ext=mp4][vcodec^=avc1][height<=480]+ba[ext=m4a]/bv*[vcodec^=avc1][height<=480]+ba[ext=m4a]/b[ext=mp4][vcodec^=avc1][height<=480]/bv*[ext=mp4][height<=480]+ba[ext=m4a]/b[ext=mp4][height<=480]/bv*[height<=480]+ba/b',
        'mp3' => 'ba/b',
    ];

    /**
     * URL'nin desteklenen bir platforma (YouTube / Instagram) ait olduğunu doğrula.
     *
     * @return array{valid: bool, platform: string|null, reason: string|null}
     */
    public function validateUrl(string $url): array
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 500) {
            return ['valid' => false, 'platform' => null, 'reason' => 'Geçersiz URL uzunluğu'];
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            return ['valid' => false, 'platform' => null, 'reason' => 'URL ayrıştırılamadı'];
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return ['valid' => false, 'platform' => null, 'reason' => 'Sadece http/https destekleniyor'];
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            if ($host === 'youtu.be' && strlen(trim($path, '/')) > 0) {
                return ['valid' => true, 'platform' => 'youtube', 'reason' => null];
            }

            foreach (self::YOUTUBE_PATH_PREFIXES as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return ['valid' => true, 'platform' => 'youtube', 'reason' => null];
                }
            }

            return ['valid' => false, 'platform' => null, 'reason' => 'Desteklenmeyen YouTube bağlantı türü (video, shorts veya canlı yayın linki girin)'];
        }

        if (in_array($host, self::INSTAGRAM_HOSTS, true)) {
            foreach (self::INSTAGRAM_PATH_PREFIXES as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return ['valid' => true, 'platform' => 'instagram', 'reason' => null];
                }
            }

            return ['valid' => false, 'platform' => null, 'reason' => 'Desteklenmeyen Instagram bağlantı türü (reels veya gönderi linki girin)'];
        }

        return ['valid' => false, 'platform' => null, 'reason' => 'Sadece YouTube ve Instagram bağlantıları destekleniyor'];
    }

    public function isValidFormat(string $format): bool
    {
        return array_key_exists($format, self::FORMAT_MAP);
    }

    /**
     * Videonun meta bilgilerini indirmeden çek.
     *
     * @return array{title: string, thumbnail: string|null, duration: int, uploader: string|null, maxHeight: int|null}
     */
    public function fetchInfo(string $url): array
    {
        $arguments = [
            $this->binary(),
            '--dump-single-json',
            '--no-playlist',
            '--no-warnings',
            '--skip-download',
            ...$this->commonArguments(),
            $url,
        ];

        $result = Process::timeout((int) config('security.video.info_timeout', 60))->run($arguments);

        if (! $result->successful()) {
            throw new \RuntimeException($this->friendlyError($result->errorOutput()));
        }

        $data = json_decode($result->output(), true);

        if (! is_array($data) || empty($data['title'])) {
            throw new \RuntimeException('Video bilgisi alınamadı.');
        }

        $maxHeight = null;
        foreach ($data['formats'] ?? [] as $format) {
            if (! empty($format['height']) && ($format['vcodec'] ?? 'none') !== 'none') {
                $maxHeight = max($maxHeight ?? 0, (int) $format['height']);
            }
        }

        return [
            'title' => (string) $data['title'],
            'thumbnail' => $data['thumbnail'] ?? null,
            'duration' => (int) ($data['duration'] ?? 0),
            'uploader' => $data['uploader'] ?? ($data['channel'] ?? null),
            'maxHeight' => $maxHeight,
        ];
    }

    /**
     * Videoyu sunucuya indir ve dosya bilgilerini döndür.
     *
     * @return array{path: string, ext: string, size: int}
     */
    public function download(string $url, string $formatKey): array
    {
        if (! $this->isValidFormat($formatKey)) {
            throw new \InvalidArgumentException('Geçersiz format seçimi.');
        }

        $outputDir = storage_path('app/private/videos');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $fileId = uniqid('vid_');
        $maxSizeMb = (int) config('security.video.max_filesize_mb', 500);

        $arguments = [
            $this->binary(),
            '-f', self::FORMAT_MAP[$formatKey],
            '--no-playlist',
            '--no-warnings',
            '--no-progress',
            '--max-filesize', "{$maxSizeMb}M",
            '-o', $outputDir.'/'.$fileId.'.%(ext)s',
            ...$this->commonArguments(),
        ];

        if ($formatKey === 'mp3') {
            array_push($arguments, '-x', '--audio-format', 'mp3', '--audio-quality', '0');
        } else {
            array_push($arguments, '--merge-output-format', 'mp4');
        }

        $arguments[] = $url;

        $result = Process::timeout((int) config('security.video.process_timeout', 300))->run($arguments);

        $files = glob($outputDir.'/'.$fileId.'.*') ?: [];
        $finalFiles = array_filter($files, fn (string $f) => ! str_ends_with($f, '.part') && ! str_ends_with($f, '.ytdl'));

        if (empty($finalFiles)) {
            foreach ($files as $leftover) {
                @unlink($leftover);
            }

            if (str_contains($result->output().$result->errorOutput(), 'max-filesize')) {
                throw new \RuntimeException("Dosya çok büyük (maks {$maxSizeMb}MB).");
            }

            throw new \RuntimeException($this->friendlyError($result->errorOutput()));
        }

        $path = array_values($finalFiles)[0];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExtensions = (array) config('security.video.allowed_output_extensions', ['mp4', 'mp3', 'm4a', 'webm', 'mkv', 'mov']);

        if (! in_array($extension, $allowedExtensions, true)) {
            @unlink($path);

            throw new \RuntimeException('Beklenmeyen çıktı formatı, indirme iptal edildi.');
        }

        return [
            'path' => $path,
            'ext' => $extension,
            'size' => (int) filesize($path),
        ];
    }

    /**
     * Video başlığından güvenli bir indirme dosya adı üret.
     */
    public function sanitizeFilename(string $title, string $extension): string
    {
        $safe = Str::of($title)
            ->replaceMatches('/[\\\\\/:*?"<>|]+/', ' ')
            ->squish()
            ->limit(80, '')
            ->trim()
            ->toString();

        return ($safe !== '' ? $safe : 'video').'.'.$extension;
    }

    public function cleanup(string $path): void
    {
        if (file_exists($path)) {
            File::delete($path);
        }
    }

    private function binary(): string
    {
        return (string) config('services.ytdlp.binary', 'yt-dlp');
    }

    /** @return array<int, string> */
    private function commonArguments(): array
    {
        $arguments = [];

        $ffmpegLocation = config('services.ytdlp.ffmpeg_location');
        if (is_string($ffmpegLocation) && $ffmpegLocation !== '') {
            array_push($arguments, '--ffmpeg-location', $ffmpegLocation);
        }

        $cookiesFile = config('services.ytdlp.cookies_file');
        if (is_string($cookiesFile) && $cookiesFile !== '' && file_exists($cookiesFile)) {
            array_push($arguments, '--cookies', $cookiesFile);
        }

        return $arguments;
    }

    /**
     * yt-dlp hata çıktısını kullanıcıya gösterilebilir mesaja çevir.
     */
    private function friendlyError(string $errorOutput): string
    {
        $patterns = [
            'Private video' => 'Bu video gizli, indirilemiyor.',
            'Video unavailable' => 'Video bulunamadı veya kaldırılmış.',
            'This video is not available' => 'Video bulunamadı veya kaldırılmış.',
            'Sign in to confirm your age' => 'Yaş kısıtlamalı içerik indirilemiyor.',
            'age-restricted' => 'Yaş kısıtlamalı içerik indirilemiyor.',
            'login required' => 'Bu içerik giriş gerektiriyor, indirilemiyor.',
            'Requested content is not available' => 'İçerik bulunamadı veya gizli bir hesaba ait.',
            'rate-limit' => 'Platform geçici olarak istekleri sınırladı, birazdan tekrar deneyin.',
            'not a valid URL' => 'Geçersiz video bağlantısı.',
            'Unsupported URL' => 'Bu bağlantı türü desteklenmiyor.',
            'is not recognized' => 'Video indirme aracı sunucuda kurulu değil.',
            'command not found' => 'Video indirme aracı sunucuda kurulu değil.',
            'No such file or directory' => 'Video indirme aracı sunucuda kurulu değil.',
            'HTTP Error 404' => 'Video bulunamadı veya kaldırılmış.',
            'HTTP Error 429' => 'Platform geçici olarak istekleri sınırladı, birazdan tekrar deneyin.',
        ];

        foreach ($patterns as $needle => $message) {
            if (stripos($errorOutput, $needle) !== false) {
                return $message;
            }
        }

        return 'Video işlenemedi, bağlantıyı kontrol edip tekrar deneyin.';
    }
}
