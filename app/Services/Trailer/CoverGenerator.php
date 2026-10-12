<?php

namespace App\Services\Trailer;

use App\Services\TmdbClient;
use App\Support\Image\Canvas;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Claude (MCP) için kapak üretimi: film bul → kapakları üret → önizleme ve
 * indirme bağlantısı ver.
 *
 * Panelden farkı: Livewire durumu yok, her çağrı kendi klasörüne yazar ve
 * dosyalar giriş gerektirmeyen, süreli imzalı bağlantıyla indirilir (Claude
 * sohbetinden telefonda açılabilsin diye).
 */
final class CoverGenerator
{
    /** İndirme bağlantıları ve üretilen klasörler bu kadar yaşar. */
    public const LINK_DAYS = 3;

    /** Görsel yükleme sayfasının bağlantısı bu kadar dakika geçerli. */
    public const UPLOAD_MINUTES = 30;

    /** Yüklenen görsellerin klasörü; üretim klasörlerinin yanında durur. */
    private const UPLOADS = 'yuklenen';

    private const UPLOAD_FILES = ['afis' => 'afis.jpg', 'arka_plan' => 'arka-plan.jpg'];

    private const MAX_REMOTE_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private readonly TmdbClient $tmdb,
        private readonly ArtworkFetcher $artwork,
        private readonly ThumbnailComposer $composer,
    ) {}

    /**
     * TMDB'de film/dizi ara; yıl verilirse o yıla ait olanlar başa alınır.
     *
     * @return array<int, array{id: int, type: string, title: string, original: string, year: string}>
     */
    public function search(string $query, ?int $year = null): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $data = $this->tmdb->remember(
            'trailer_studio_search_'.md5(mb_strtolower($query)),
            now()->addMinutes(30),
            fn () => $this->tmdb->get('/search/multi', [
                'query' => $query,
                'language' => 'tr-TR',
                'include_adult' => 'false',
            ]),
        );

        if (! is_array($data)) {
            throw new RuntimeException('TMDB yanıt vermedi. Birazdan tekrar deneyin.');
        }

        return collect($data['results'] ?? [])
            ->filter(fn ($item) => is_array($item) && in_array($item['media_type'] ?? '', ['movie', 'tv'], true))
            ->map(fn (array $item): array => [
                'id' => (int) $item['id'],
                'type' => (string) $item['media_type'],
                'title' => (string) ($item['title'] ?? $item['name'] ?? '—'),
                'original' => (string) ($item['original_title'] ?? $item['original_name'] ?? ''),
                'year' => substr((string) ($item['release_date'] ?? $item['first_air_date'] ?? ''), 0, 4),
            ])
            ->sortBy(fn (array $item, int $index): array => [$year !== null && $item['year'] !== (string) $year ? 1 : 0, $index])
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Seçilebilir görseller: arka plan, afiş ya da logo. Sıra panelle aynı,
     * böylece "3 numaralı arka plan" her iki yerde de aynı görsel olur.
     *
     * @return array<int, array{path: string, dil: string|null}>
     */
    public function options(string $type, int $id, string $kind): array
    {
        return match ($kind) {
            'afis' => $this->artwork->posters($type, $id),
            'logo' => $this->artwork->logos($type, $id),
            default => $this->artwork->backdrops($type, $id),
        };
    }

    /**
     * TMDB kaydından payload; seçilen arka plan/afiş/logo uygulanır.
     *
     * @param  array{arka_plan?: string|null, afis?: string|null, logo?: string|null}  $choices  TMDB dosya yolları
     */
    public function tmdbPayload(string $type, int $id, array $choices = []): ThumbnailPayload
    {
        $payload = $this->artwork->fetch($type, $id);

        if ($payload === null) {
            throw new RuntimeException('TMDB kaydı alınamadı. Bağlantı kesik olabilir.');
        }

        if (($choices['arka_plan'] ?? null) !== null) {
            $payload = $payload->withBackdrop($this->artwork->download($choices['arka_plan'], (string) config('trailer.sizes.backdrop')));
        }

        if (($choices['afis'] ?? null) !== null) {
            $payload = $payload->withPoster($this->artwork->download($choices['afis'], (string) config('trailer.sizes.poster')));
        }

        if (($choices['logo'] ?? null) !== null) {
            $payload = $payload->withLogo($this->artwork->download($choices['logo'], (string) config('trailer.sizes.logo')));
        }

        return $payload;
    }

    /** TMDB'de olmayan yapım: yalnızca yüklenen görseller ve elle yazılan ad. */
    public function manualPayload(string $title, ?string $meta = null): ThumbnailPayload
    {
        $meta = $meta !== null && trim($meta) !== '' ? trim($meta) : null;

        return new ThumbnailPayload(title: $title, ribbon: (string) config('trailer.defaults.ribbon'), meta: $meta);
    }

    /**
     * Kapakları üret.
     *
     * @param  array<int, string>  $formats  'video' ve/veya 'shorts'
     * @return array{title: string, batch: string, notice: string|null, rendered: array<string, string>}
     */
    public function generate(int $userId, ThumbnailPayload $payload, CoverSettings $settings, array $formats, bool $manual = false): array
    {
        if (! $payload->hasArtwork()) {
            throw new RuntimeException('Kapak için görsel yok: TMDB\'de afiş ya da arka plan bulunmadı. gorsel_yukleme_linki ile kendi görselini yükle ya da görselin adresini (afis_url / arka_plan_url) ver.');
        }

        [$payload, $notice] = $settings->apply($payload, $manual);

        $batch = Str::lower(Str::random(16));
        $directory = $this->userDirectory($userId).'/'.$batch;

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Kapak klasörü oluşturulamadı.');
        }

        $this->pruneOldBatches($userId);

        $rendered = $this->composer->renderAll($payload, $directory, $formats);

        if ($rendered === []) {
            throw new RuntimeException('Bu biçim için gereken görseller eksik; başka bir biçim ya da görsel deneyin.');
        }

        return [
            'title' => $payload->title,
            'batch' => $batch,
            'notice' => $notice,
            'rendered' => $rendered,
        ];
    }

    /** Telefondan görsel yüklenecek sayfanın süreli bağlantısı. */
    public function uploadUrl(int $userId): string
    {
        return URL::temporarySignedRoute('mcp.covers.upload', now()->addMinutes(self::UPLOAD_MINUTES), ['user' => $userId]);
    }

    /**
     * Yüklenen ya da adresten indirilen görseli kullanıcının görsel yuvasına
     * yaz. Her çeşitten tek görsel tutulur; yenisi eskisinin yerine geçer.
     */
    public function storeUpload(int $userId, string $kind, string $source): string
    {
        $directory = $this->userDirectory($userId).'/'.self::UPLOADS;

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Görsel klasörü oluşturulamadı.');
        }

        try {
            $canvas = Canvas::open($source);

            // Telefon fotoğrafları 4000 px'i geçiyor; kapak için 2400 px fazlasıyla yeter.
            if (max($canvas->width(), $canvas->height()) > 2400) {
                $canvas = $canvas->contain(2400, 2400);
            }

            return $canvas->toJpeg($directory.'/'.self::UPLOAD_FILES[$kind], 92);
        } catch (\Throwable) {
            throw new RuntimeException('Görsel okunamadı; JPG, PNG ya da WebP olmalı.');
        } finally {
            Canvas::flushDecoded();
        }
    }

    /**
     * Son yüklenen görseller; indirme bağlantıları kadar saklanır.
     *
     * @return array{afis?: string, arka_plan?: string}
     */
    public function uploads(int $userId): array
    {
        $cutoff = now()->subDays(self::LINK_DAYS)->getTimestamp();
        $found = [];

        foreach (self::UPLOAD_FILES as $kind => $file) {
            $path = $this->userDirectory($userId).'/'.self::UPLOADS.'/'.$file;

            if (is_file($path) && (@filemtime($path) ?: 0) >= $cutoff) {
                $found[$kind] = $path;
            }
        }

        return $found;
    }

    /**
     * İnternetteki bir görseli indirip görsel yuvasına koy.
     *
     * Sunucu bu adrese kendisi istek attığı için iç ağa (127.0.0.1, 10.x,
     * bulut metadata adresi…) yönelen adresler reddedilir; çözülen IP isteğe
     * sabitlenir ki DNS arada değişip iç ağa dönemesin. Yönlendirmeler de
     * her adımda aynı denetimden geçer.
     */
    public function storeFromUrl(int $userId, string $kind, string $url): string
    {
        for ($hop = 0; ; $hop++) {
            [$host, $port, $ip] = $this->publicTarget($url);

            $response = Http::timeout(20)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (BannerArchive)', 'Accept' => 'image/*'])
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]]])
                ->get($url);

            if (! $response->redirect() || $hop === 3) {
                break;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri((string) $response->header('Location')));
        }

        if (! $response->successful()) {
            throw new RuntimeException("Görsel indirilemedi (HTTP {$response->status()}). Doğrudan görsel dosyasına giden bir adres verin.");
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_REMOTE_BYTES) {
            throw new RuntimeException('Görsel boş ya da 15 MB\'tan büyük.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'kapak');

        try {
            file_put_contents($temp, $body);

            return $this->storeUpload($userId, $kind, $temp);
        } finally {
            @unlink($temp);
        }
    }

    /** Giriş gerektirmeyen, süreli indirme bağlantısı. */
    public function downloadUrl(int $userId, string $batch, string $path): string
    {
        return URL::temporarySignedRoute('mcp.covers.download', now()->addDays(self::LINK_DAYS), [
            'user' => $userId,
            'batch' => $batch,
            'file' => basename($path),
        ]);
    }

    /** Claude'a gösterilecek küçük JPEG önizleme (kotayı az harcasın diye). */
    public function preview(string $path, int $maxSide = 640): string
    {
        try {
            $canvas = Canvas::open($path);
            $scale = $maxSide / max($canvas->width(), $canvas->height());

            return $this->jpegBytes($canvas->resized(
                max(1, (int) round($canvas->width() * $scale)),
                max(1, (int) round($canvas->height() * $scale)),
            ));
        } finally {
            Canvas::flushDecoded();
        }
    }

    /**
     * TMDB seçeneğinin küçük önizlemesi. Logolar şeffaf olduğu için koyu gri
     * zemine oturtulur; beyaz logo da görünsün.
     */
    public function optionPreview(string $tmdbPath, string $kind): ?string
    {
        $file = $this->artwork->download($tmdbPath, match ($kind) {
            'afis' => 'w185',
            default => 'w300',
        });

        if ($file === null) {
            return null;
        }

        try {
            $image = Canvas::open($file);

            if ($kind === 'logo') {
                $image = Canvas::create(320, 140, '#3a3a3a')->placeCentered($image->contain(288, 112), 160, 70);
            }

            return $this->jpegBytes($image);
        } catch (\Throwable) {
            return null;
        } finally {
            Canvas::flushDecoded();
        }
    }

    /** İmzalı bağlantının işaret ettiği dosya; yoksa null. */
    public function storedFile(int $userId, string $batch, string $file): ?string
    {
        if (preg_match('/^[a-z0-9]{16}$/', $batch) !== 1 || preg_match('/^[a-z0-9\-_]+\.jpg$/', $file) !== 1) {
            return null;
        }

        $path = $this->userDirectory($userId).'/'.$batch.'/'.$file;

        return is_file($path) ? $path : null;
    }

    /**
     * Adres yalnızca internetteki bir sunucuya gidebilir.
     *
     * @return array{0: string, 1: int, 2: string} host, port, sabitlenecek IP
     */
    private function publicTarget(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'])) {
            throw new RuntimeException('Görsel adresi http(s):// ile başlamalı.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, [80, 443], true)) {
            throw new RuntimeException('Görsel adresi standart bir port kullanmalı.');
        }

        $ips = gethostbynamel($host) ?: [];

        if ($ips === []) {
            throw new RuntimeException("{$host} adresi bulunamadı.");
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Bu adres iç ağa gidiyor; yalnızca internetteki görseller indirilebilir.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    private function userDirectory(int $userId): string
    {
        return rtrim((string) config('trailer.storage.mcp'), '/\\').'/'.$userId;
    }

    /** Bağlantı süresi dolmuş üretimleri sil. */
    private function pruneOldBatches(int $userId): void
    {
        $cutoff = now()->subDays(self::LINK_DAYS)->getTimestamp();

        foreach (glob($this->userDirectory($userId).'/*', GLOB_ONLYDIR) ?: [] as $batch) {
            if ((@filemtime($batch) ?: 0) >= $cutoff) {
                continue;
            }

            foreach (glob($batch.'/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($batch);
        }
    }

    private function jpegBytes(Canvas $canvas): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'kapak');

        try {
            $canvas->toJpeg($temp, 78);

            return (string) file_get_contents($temp);
        } finally {
            @unlink($temp);
        }
    }
}
