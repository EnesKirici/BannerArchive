<?php

use App\Models\BlockedIp;
use App\Models\SecurityLog;
use App\Services\VideoDownloaderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.tool')] #[Title('Video İndirici')] class extends Component
{
    public string $url = '';

    public string $format = 'mp4_best';

    /** @var array{title: string, thumbnail: string|null, duration: int, uploader: string|null, maxHeight: int|null, platform: string}|null */
    public ?array $videoInfo = null;

    public string $downloadUrl = '';

    public string $downloadName = '';

    public string $message = '';

    public string $messageType = '';

    public function fetchInfo(): void
    {
        $this->reset(['videoInfo', 'downloadUrl', 'downloadName', 'message', 'messageType']);

        $this->validate([
            'url' => ['required', 'string', 'max:500'],
        ], [
            'url.required' => 'Lütfen bir video bağlantısı girin.',
            'url.max' => 'Bağlantı çok uzun.',
        ]);

        $ip = request()->ip();
        $limitKey = "video-info:{$ip}";
        $maxAttempts = (int) config('security.video.rate_limit_info', 10);

        if (RateLimiter::tooManyAttempts($limitKey, $maxAttempts)) {
            $this->message = 'Çok fazla istek gönderdiniz, lütfen bir dakika bekleyin.';
            $this->messageType = 'error';

            return;
        }

        RateLimiter::hit($limitKey, 60);

        $service = app(VideoDownloaderService::class);
        $validation = $service->validateUrl($this->url);

        if (! $validation['valid']) {
            $this->trackSuspiciousUrl($ip, $this->url, $validation['reason']);
            $this->message = $validation['reason'];
            $this->messageType = 'error';

            return;
        }

        try {
            $info = $service->fetchInfo(trim($this->url));
            $this->videoInfo = [...$info, 'platform' => $validation['platform']];
        } catch (\RuntimeException $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        } catch (\Throwable $e) {
            Log::channel('daily')->error('Video bilgisi çekilemedi', ['url' => $this->url, 'error' => $e->getMessage()]);
            $this->message = 'Video bilgisi alınamadı, lütfen tekrar deneyin.';
            $this->messageType = 'error';
        }
    }

    public function download(): void
    {
        $this->reset(['downloadUrl', 'downloadName', 'message', 'messageType']);

        if ($this->videoInfo === null) {
            $this->message = 'Önce bir video bağlantısı getirin.';
            $this->messageType = 'error';

            return;
        }

        $service = app(VideoDownloaderService::class);

        if (! $service->isValidFormat($this->format)) {
            $this->message = 'Geçersiz format seçimi.';
            $this->messageType = 'error';

            return;
        }

        $validation = $service->validateUrl($this->url);

        if (! $validation['valid']) {
            $this->message = $validation['reason'];
            $this->messageType = 'error';

            return;
        }

        $ip = request()->ip();
        $limitKey = "video-download:{$ip}";
        $maxAttempts = (int) config('security.video.rate_limit_download', 3);

        if (RateLimiter::tooManyAttempts($limitKey, $maxAttempts)) {
            $this->message = 'Çok fazla indirme isteği gönderdiniz, lütfen bir dakika bekleyin.';
            $this->messageType = 'error';

            return;
        }

        $ipLock = Cache::lock("video-dl-ip-{$ip}", (int) config('security.video.process_timeout', 300) + 60);

        if (! $ipLock->get()) {
            $this->message = 'Devam eden bir indirmeniz var, tamamlanmasını bekleyin.';
            $this->messageType = 'error';

            return;
        }

        $activeKey = 'video_dl_active_count';
        $maxConcurrent = (int) config('security.video.max_concurrent_downloads', 3);

        if ((int) Cache::get($activeKey, 0) >= $maxConcurrent) {
            $ipLock->release();
            $this->message = 'Sunucu şu anda yoğun, lütfen birkaç dakika sonra tekrar deneyin.';
            $this->messageType = 'error';

            return;
        }

        RateLimiter::hit($limitKey, 60);

        set_time_limit((int) config('security.video.process_timeout', 300) + 30);

        Cache::put($activeKey, (int) Cache::get($activeKey, 0) + 1, 900);

        try {
            $result = $service->download(trim($this->url), $this->format);

            $token = Str::random(40);
            $downloadName = $service->sanitizeFilename($this->videoInfo['title'], $result['ext']);

            Cache::put("video_download_{$token}", [
                'path' => $result['path'],
                'name' => $downloadName,
            ], now()->addMinutes(30));

            $this->downloadUrl = route('tools.video-downloader.file', ['token' => $token]);
            $this->downloadName = $downloadName;
            $this->message = 'Video hazır, indirme başlıyor...';
            $this->messageType = 'success';

            $this->dispatch('video-ready', url: $this->downloadUrl);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        } catch (\Throwable $e) {
            Log::channel('daily')->error('Video indirilemedi', ['url' => $this->url, 'error' => $e->getMessage()]);
            $this->message = 'Video indirilemedi, lütfen tekrar deneyin.';
            $this->messageType = 'error';
        } finally {
            Cache::put($activeKey, max(0, (int) Cache::get($activeKey, 1) - 1), 900);
            $ipLock->release();
        }
    }

    public function resetAll(): void
    {
        $this->reset(['url', 'videoInfo', 'downloadUrl', 'downloadName', 'message', 'messageType', 'format']);
    }

    private function trackSuspiciousUrl(string $ip, string $url, string $reason): void
    {
        $config = config('security.video');
        $cacheKey = "suspicious_video_url_{$ip}";
        $window = ($config['suspicious_window'] ?? 30) * 60;

        $attempts = (int) Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $attempts, $window);

        Log::channel('daily')->warning('Geçersiz video URL denemesi', [
            'ip' => $ip,
            'url' => $url,
            'reason' => $reason,
            'attempt' => $attempts,
            'user_agent' => request()->userAgent(),
        ]);

        SecurityLog::record(
            ip: $ip,
            eventType: 'suspicious_video_url',
            description: "Geçersiz video URL: {$reason}",
            requestCount: $attempts,
            userAgent: request()->userAgent(),
            url: request()->fullUrl(),
            metadata: ['submitted_url' => Str::limit($url, 200), 'reason' => $reason],
        );

        if ($attempts >= ($config['ban_after_attempts'] ?? 10)) {
            BlockedIp::autoBan(
                ip: $ip,
                reason: "Geçersiz video URL denemesi: {$attempts} deneme",
                banType: 'suspicious_video_url',
                requestCount: $attempts,
            );

            Cache::forget($cacheKey);

            abort(403, 'Erişiminiz engellenmiştir.');
        }
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }

        return $seconds >= 3600
            ? gmdate('G:i:s', $seconds)
            : gmdate('i:s', $seconds);
    }
};
?>

<div
    class="relative max-w-3xl mx-auto px-4 sm:px-6 py-8 md:py-12"
    x-data
    x-on:video-ready.window="setTimeout(() => { window.location.href = $event.detail.url }, 400)"
>
    <style>
        @keyframes vdFloat1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(60px, -80px) scale(1.1); }
            66% { transform: translate(-40px, 50px) scale(0.95); }
        }
        @keyframes vdFloat2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-70px, 60px) scale(1.15); }
            66% { transform: translate(50px, -70px) scale(0.9); }
        }
    </style>

    {{-- Animated Background --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-cyan-600/8 blur-[130px]" style="animation: vdFloat1 25s ease-in-out infinite"></div>
        <div class="absolute -bottom-32 -right-32 w-[450px] h-[450px] rounded-full bg-purple-700/8 blur-[120px]" style="animation: vdFloat2 30s ease-in-out infinite"></div>
    </div>

    {{-- Hero --}}
    <div class="text-center mb-10">
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-3 bg-clip-text text-transparent bg-linear-to-r from-white via-cyan-200 to-cyan-500">
            Video İndirici
        </h1>
        <p class="text-neutral-400 max-w-lg mx-auto">
            YouTube video ve Shorts, Instagram Reels ve gönderi videolarını
            MP4 veya MP3 olarak hızlıca indirin.
        </p>
    </div>

    {{-- URL Girişi --}}
    <form wire:submit="fetchInfo" class="mb-6">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-neutral-500 pointer-events-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-4 4a4 4 0 01-5.656-5.656l1.102-1.101m14.828-2.242a4 4 0 000-5.656l-4-4a4 4 0 00-5.656 0l-1.1 1.1"/>
                    </svg>
                </div>
                <input
                    type="url"
                    wire:model="url"
                    placeholder="https://www.youtube.com/watch?v=... veya instagram.com/reel/..."
                    class="w-full bg-neutral-900 border border-white/10 focus:border-cyan-500/60 focus:ring-2 focus:ring-cyan-500/20 rounded-xl pl-12 pr-4 py-3.5 text-sm text-white placeholder-neutral-600 outline-none transition-all"
                    autocomplete="off"
                    spellcheck="false"
                >
            </div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="fetchInfo"
                class="bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl px-6 py-3.5 font-bold text-sm flex items-center justify-center gap-2 transition-all shadow-lg shadow-cyan-600/20 shrink-0"
            >
                <span wire:loading.remove wire:target="fetchInfo" class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Videoyu Getir
                </span>
                <span wire:loading wire:target="fetchInfo" class="flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Getiriliyor...
                </span>
            </button>
        </div>
    </form>

    {{-- Flash Mesajları --}}
    @if($message !== '')
        <div class="mb-6 p-4 rounded-xl flex items-center gap-3
            {{ $messageType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border border-red-500/20 text-red-400' }}">
            @if($messageType === 'success')
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            @else
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            @endif
            <span class="text-sm">{{ $message }}</span>
        </div>
    @endif

    {{-- Validasyon Hataları --}}
    @if($errors->any())
        <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400">
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($videoInfo !== null)
        {{-- Video Bilgi Kartı --}}
        <div class="bg-neutral-900 rounded-2xl border border-white/5 overflow-hidden mb-6">
            <div class="flex flex-col sm:flex-row">
                {{-- Thumbnail --}}
                <div class="sm:w-64 shrink-0 bg-[#0c0c0c] flex items-center justify-center">
                    @if($videoInfo['thumbnail'])
                        <img
                            src="{{ $videoInfo['thumbnail'] }}"
                            alt="{{ $videoInfo['title'] }}"
                            referrerpolicy="no-referrer"
                            class="w-full h-full max-h-48 sm:max-h-none object-cover"
                            onerror="this.style.display='none'"
                        >
                    @else
                        <div class="py-12 text-neutral-700">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    @endif
                </div>

                {{-- Bilgiler --}}
                <div class="flex-1 p-5 min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        @if($videoInfo['platform'] === 'youtube')
                            <span class="px-2 py-0.5 rounded bg-red-600/20 text-red-400 text-[10px] font-bold uppercase tracking-wider">YouTube</span>
                        @else
                            <span class="px-2 py-0.5 rounded bg-fuchsia-600/20 text-fuchsia-400 text-[10px] font-bold uppercase tracking-wider">Instagram</span>
                        @endif
                        @if($videoInfo['maxHeight'])
                            <span class="px-2 py-0.5 rounded bg-white/10 text-neutral-300 text-[10px] font-mono font-bold">{{ $videoInfo['maxHeight'] }}p</span>
                        @endif
                        @if($videoInfo['duration'] > 0)
                            <span class="text-xs text-neutral-500 font-mono">{{ $this->formatDuration($videoInfo['duration']) }}</span>
                        @endif
                    </div>

                    <h2 class="text-base font-semibold text-white/90 mb-1 line-clamp-2">{{ $videoInfo['title'] }}</h2>

                    @if($videoInfo['uploader'])
                        <p class="text-xs text-neutral-500 mb-4">{{ $videoInfo['uploader'] }}</p>
                    @endif

                    {{-- Format Seçimi --}}
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach([
                            'mp4_best' => ['En İyi', 'MP4'],
                            'mp4_720' => ['720p', 'MP4'],
                            'mp4_480' => ['480p', 'MP4'],
                            'mp3' => ['Sadece Ses', 'MP3'],
                        ] as $formatKey => [$formatLabel, $formatExt])
                            <button
                                wire:click="$set('format', '{{ $formatKey }}')"
                                type="button"
                                class="px-3 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5
                                    {{ $format === $formatKey
                                        ? 'bg-cyan-600 text-white shadow-lg shadow-cyan-600/25'
                                        : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700 hover:text-neutral-300 border border-white/5' }}"
                            >
                                {{ $formatLabel }}
                                <span class="font-mono text-[10px] uppercase opacity-60">{{ $formatExt }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Aksiyonlar --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            wire:click="download"
                            wire:loading.attr="disabled"
                            wire:target="download"
                            type="button"
                            class="bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl px-5 py-2.5 font-bold text-sm flex items-center gap-2 transition-all shadow-lg shadow-cyan-600/20"
                        >
                            <span wire:loading.remove wire:target="download" class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                İndir
                            </span>
                            <span wire:loading wire:target="download" class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Hazırlanıyor...
                            </span>
                        </button>

                        @if($downloadUrl !== '')
                            <a
                                href="{{ $downloadUrl }}"
                                class="bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl px-5 py-2.5 font-bold text-sm flex items-center gap-2 transition-all shadow-lg shadow-emerald-600/20"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Tekrar İndir
                            </a>
                        @endif

                        <button
                            wire:click="resetAll"
                            type="button"
                            class="px-4 py-2.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-400 hover:text-neutral-300 text-sm rounded-xl transition-all border border-white/5"
                        >
                            Yeni Video
                        </button>
                    </div>

                    <p wire:loading wire:target="download" class="text-xs text-neutral-500 mt-3">
                        Video sunucuda hazırlanıyor, dosya boyutuna göre bu işlem biraz sürebilir...
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Boş Durum --}}
    @if($videoInfo === null)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
            <div class="bg-neutral-900/50 rounded-xl border border-white/5 p-6 text-center">
                <div class="w-10 h-10 mx-auto mb-3 rounded-lg bg-red-600/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold mb-1">YouTube & Shorts</h3>
                <p class="text-xs text-neutral-500">Video ve Shorts bağlantılarını destekler</p>
            </div>
            <div class="bg-neutral-900/50 rounded-xl border border-white/5 p-6 text-center">
                <div class="w-10 h-10 mx-auto mb-3 rounded-lg bg-fuchsia-600/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold mb-1">Instagram Reels</h3>
                <p class="text-xs text-neutral-500">Reels ve gönderi videolarını indirin</p>
            </div>
            <div class="bg-neutral-900/50 rounded-xl border border-white/5 p-6 text-center">
                <div class="w-10 h-10 mx-auto mb-3 rounded-lg bg-cyan-600/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold mb-1">MP4 & MP3</h3>
                <p class="text-xs text-neutral-500">Video veya sadece ses olarak kaydedin</p>
            </div>
        </div>
    @endif
</div>
