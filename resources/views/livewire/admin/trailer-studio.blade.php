<?php

/**
 * Kapak Stüdyosu
 * --------------
 * Film/dizi ara → seç → üç şablonun kapağını da anında gör → indir.
 *
 * Ayar değiştiğinde (şerit, logo tercihi, renk...) updated() kancası kapakları
 * yeniden üretir; böylece tasarım kararlarını canlı deneyerek verebiliyoruz.
 * Üretilen dosyalar kullanıcıya özel, dışarı kapalı klasöre yazılır.
 */

use App\Services\TmdbClient;
use App\Services\Trailer\ArtworkFetcher;
use App\Services\Trailer\ThumbnailComposer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('admin.layout')] #[Title('Kapak Stüdyosu')] class extends Component
{
    public string $query = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public ?int $selectedId = null;

    public string $selectedType = 'movie';

    public string $selectedTitle = '';

    public string $ribbonKey = 'altyazi';

    public string $customRibbon = '';

    /** auto = TMDB ne verdiyse · tr = sadece Türkçe logo · text = logo yerine başlık */
    public string $logoMode = 'auto';

    public bool $showMeta = true;

    /** marka = sabit kurumsal renk · oto = afişten çıkar · ozel = elle seçilen */
    public string $accentMode = 'marka';

    public string $accent = '';

    public bool $brand = true;

    /** Elle seçilen arka plan (TMDB dosya yolu); null = en iyisi otomatik seçilsin. */
    public ?string $backdropChoice = null;

    /** @var array<int, array{path: string, dil: string|null}> */
    public array $backdropOptions = [];

    public function mount(): void
    {
        $this->accent = (string) config('trailer.defaults.accent', '#00059e');
    }

    /** @var array<int, array<string, string>> */
    public array $thumbnails = [];

    /** @var array<string, bool> */
    public array $artwork = [];

    public ?string $logoLanguage = null;

    public ?string $notice = null;

    public ?string $error = null;

    public int $renderedAt = 0;

    public function search(TmdbClient $tmdb): void
    {
        $this->error = null;
        $this->results = [];

        $query = trim($this->query);

        if (mb_strlen($query) < 2) {
            $this->error = 'En az 2 karakter yazın.';

            return;
        }

        $data = $tmdb->remember(
            'trailer_studio_search_'.md5(mb_strtolower($query)),
            now()->addMinutes(30),
            fn () => $tmdb->get('/search/multi', [
                'query' => $query,
                'language' => 'tr-TR',
                'include_adult' => 'false',
            ]),
        );

        if (! is_array($data)) {
            $this->error = 'TMDB yanıt vermedi. Birazdan tekrar deneyin.';

            return;
        }

        $this->results = collect($data['results'] ?? [])
            ->filter(fn ($item) => is_array($item) && in_array($item['media_type'] ?? '', ['movie', 'tv'], true))
            ->take(12)
            ->map(fn (array $item) => [
                'id' => (int) $item['id'],
                'type' => (string) $item['media_type'],
                'title' => (string) ($item['title'] ?? $item['name'] ?? '—'),
                'year' => substr((string) ($item['release_date'] ?? $item['first_air_date'] ?? ''), 0, 4),
                'poster' => $item['poster_path'] ?? null,
            ])
            ->values()
            ->all();

        if ($this->results === []) {
            $this->error = 'Sonuç bulunamadı.';
        }
    }

    public function choose(int $id): void
    {
        $chosen = collect($this->results)->firstWhere('id', $id);

        if ($chosen === null) {
            return;
        }

        $this->selectedId = $id;
        $this->selectedType = (string) $chosen['type'];
        $this->selectedTitle = (string) $chosen['title'];
        $this->backdropChoice = null;

        $artwork = app(ArtworkFetcher::class);
        $this->backdropOptions = $artwork->backdrops($this->selectedType, $id);

        $this->build($artwork, app(ThumbnailComposer::class));

        // Kapaklar sayfanın altında üretiliyor; kullanıcıyı oraya götür.
        // Kısa gecikme, hedef bölümün DOM'a yerleşmesini bekliyor.
        $this->js('setTimeout(() => document.getElementById("kapaklar")?.scrollIntoView({ behavior: "smooth", block: "start" }), 80)');
    }

    public function chooseBackdrop(?string $path): void
    {
        $this->backdropChoice = $path;

        $this->build(app(ArtworkFetcher::class), app(ThumbnailComposer::class));
    }

    /** Ayarlardan biri değişince kapakları tazele. */
    public function updated(string $property, mixed $value = null): void
    {
        if ($this->selectedId === null || $property === 'query') {
            return;
        }

        $this->build(app(ArtworkFetcher::class), app(ThumbnailComposer::class));
    }

    public function build(ArtworkFetcher $artwork, ThumbnailComposer $composer): void
    {
        $this->error = null;
        $this->notice = null;
        $this->thumbnails = [];

        if ($this->selectedId === null) {
            return;
        }

        $payload = $artwork->fetch($this->selectedType, $this->selectedId);

        if ($payload === null) {
            $this->error = 'TMDB kaydı alınamadı. Bağlantı kesik olabilir.';

            return;
        }

        // Arka plan elle seçildiyse TMDB'nin sıraladığını değil, onu kullan.
        if ($this->backdropChoice !== null) {
            $payload = $payload->withBackdrop(
                $artwork->download($this->backdropChoice, (string) config('trailer.sizes.backdrop'))
            );
        }

        $this->logoLanguage = $payload->logoLanguage;
        $this->artwork = [
            'afiş' => $payload->poster !== null,
            'backdrop' => $payload->backdrop !== null,
            'logo' => $payload->logo !== null,
        ];

        $payload = $payload->with(ribbon: $this->ribbonText(), brand: $this->brand);

        $payload = match ($this->accentMode) {
            'oto' => $payload->withoutAccent(),
            'ozel' => $payload->with(accent: $this->accent),
            default => $payload->with(accent: (string) config('trailer.defaults.accent')),
        };

        if (! $this->showMeta) {
            $payload = $payload->withoutMeta();
        }

        if ($this->logoMode === 'text') {
            $payload = $payload->withoutLogo();
        } elseif ($this->logoMode === 'tr' && $payload->logoLanguage !== 'tr') {
            $payload = $payload->withoutLogo();
            $this->notice = 'TMDB’de Türkçe logo yok — başlık metni yazıldı.';
        } elseif ($payload->logo !== null && $payload->logoLanguage !== 'tr') {
            $this->notice = 'TMDB’de Türkçe logo yok, '.mb_strtoupper((string) $payload->logoLanguage).' logo kullanıldı.';
        }

        $directory = storage_path('app/private/trailer/thumbnails/'.auth()->id());

        foreach (glob($directory.'/*.jpg') ?: [] as $stale) {
            @unlink($stale);
        }

        try {
            // renderAll: vurgu rengini üç şablon için bir kez hesaplar.
            $rendered = $composer->renderAll($payload, $directory);
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = 'Kapak üretilemedi: '.$exception->getMessage();

            return;
        }

        $labels = $composer->options();

        $this->thumbnails = collect($rendered)
            ->map(fn (string $path, string $key): array => [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'file' => basename($path),
                'size' => number_format(filesize($path) / 1024).' KB',
            ])
            ->values()
            ->all();
        $this->renderedAt = now()->timestamp;
    }

    private function ribbonText(): string
    {
        if ($this->ribbonKey === 'ozel') {
            return trim($this->customRibbon) !== ''
                ? trim($this->customRibbon)
                : (string) config('trailer.defaults.ribbon');
        }

        return (string) (config('trailer.ribbons')[$this->ribbonKey] ?? config('trailer.defaults.ribbon'));
    }
};
?>

<div class="space-y-6">

    {{-- ---------------------------------------------------------------- Arama --}}
    <div class="bg-neutral-900 rounded-xl border border-white/5 p-6">
        <form wire:submit="search" class="flex flex-col sm:flex-row gap-3">
            <input wire:model="query" type="text" placeholder="Film veya dizi adı yazın…"
                   class="flex-1 bg-neutral-950 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-neutral-600 focus:outline-none focus:border-fuchsia-500/60 transition-colors">
            <button type="submit"
                    class="px-6 py-3 rounded-lg bg-fuchsia-600 hover:bg-fuchsia-500 font-semibold transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                    wire:loading.attr="disabled" wire:target="search">
                <svg wire:loading.remove wire:target="search" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <svg wire:loading wire:target="search" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Ara
            </button>
        </form>

        @if($error)
            <p class="mt-4 text-sm text-red-400">{{ $error }}</p>
        @endif

        @if($results)
            <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($results as $item)
                    <button type="button" wire:click="choose({{ $item['id'] }})" wire:key="sonuc-{{ $item['type'] }}-{{ $item['id'] }}"
                            class="group text-left rounded-lg overflow-hidden border transition-colors {{ $selectedId === $item['id'] ? 'border-fuchsia-500' : 'border-white/5 hover:border-white/20' }}">
                        <div class="aspect-[2/3] bg-neutral-950">
                            @if($item['poster'])
                                <img src="https://image.tmdb.org/t/p/w185{{ $item['poster'] }}" alt="" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-neutral-700 text-xs">görsel yok</div>
                            @endif
                        </div>
                        <div class="p-2">
                            <p class="text-xs font-medium truncate group-hover:text-fuchsia-400 transition-colors">{{ $item['title'] }}</p>
                            <p class="text-[11px] text-neutral-500">{{ $item['year'] ?: '—' }} · {{ $item['type'] === 'tv' ? 'Dizi' : 'Film' }}</p>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if($selectedId)
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- ------------------------------------------------------- Ayarlar --}}
            <div class="bg-neutral-900 rounded-xl border border-white/5 p-6 space-y-6 h-fit">
                <div>
                    <h3 class="font-semibold text-lg">{{ $selectedTitle }}</h3>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($artwork as $name => $found)
                            <span class="px-2 py-0.5 text-[11px] rounded {{ $found ? 'bg-emerald-500/10 text-emerald-400' : 'bg-neutral-800 text-neutral-500' }}">
                                {{ $name }} {{ $found ? '✓' : '✗' }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Şerit metni</label>
                    <div class="space-y-2">
                        @foreach(config('trailer.ribbons') as $key => $text)
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors {{ $ribbonKey === $key ? 'border-fuchsia-500/60 bg-fuchsia-500/5' : 'border-white/5 hover:border-white/15' }}">
                                <input type="radio" wire:model.live="ribbonKey" value="{{ $key }}" class="accent-fuchsia-500">
                                <span class="text-sm">{{ $text }}</span>
                            </label>
                        @endforeach
                        <label class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors {{ $ribbonKey === 'ozel' ? 'border-fuchsia-500/60 bg-fuchsia-500/5' : 'border-white/5 hover:border-white/15' }}">
                            <input type="radio" wire:model.live="ribbonKey" value="ozel" class="accent-fuchsia-500">
                            <span class="text-sm">Özel metin</span>
                        </label>
                        @if($ribbonKey === 'ozel')
                            <input wire:model.blur="customRibbon" type="text" placeholder="Örn. Türkçe Fragman"
                                   class="w-full bg-neutral-950 border border-white/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-fuchsia-500/60">
                        @endif
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Film adı nasıl yazılsın</label>
                    <div class="space-y-2">
                        @foreach([
                            'auto' => 'Resmî logo (TMDB ne verirse)',
                            'tr' => 'Sadece Türkçe logo — yoksa yazı',
                            'text' => 'Her zaman yazı olarak',
                        ] as $key => $text)
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors {{ $logoMode === $key ? 'border-fuchsia-500/60 bg-fuchsia-500/5' : 'border-white/5 hover:border-white/15' }}">
                                <input type="radio" wire:model.live="logoMode" value="{{ $key }}" class="accent-fuchsia-500">
                                <span class="text-sm">{{ $text }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if($logoLanguage)
                        <p class="mt-2 text-xs text-neutral-500">Bulunan logo dili: <span class="font-mono uppercase">{{ $logoLanguage }}</span></p>
                    @endif
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="showMeta" class="accent-fuchsia-500 w-4 h-4">
                    <span class="text-sm">Yıl • tür satırını göster</span>
                </label>

                @if($backdropOptions)
                    <div>
                        <label class="block text-sm font-medium mb-2">Arka plan görseli</label>
                        <div class="grid grid-cols-3 gap-2 max-h-64 overflow-y-auto pr-1">
                            <button type="button" wire:click="chooseBackdrop(null)"
                                    class="aspect-video rounded-md border text-[11px] text-neutral-400 flex items-center justify-center transition-colors {{ $backdropChoice === null ? 'border-fuchsia-500 text-white' : 'border-white/10 hover:border-white/30' }}">
                                Otomatik
                            </button>
                            @foreach($backdropOptions as $option)
                                <button type="button" wire:click="chooseBackdrop('{{ $option['path'] }}')" wire:key="arkaplan-{{ $loop->index }}"
                                        class="relative aspect-video rounded-md overflow-hidden border transition-colors {{ $backdropChoice === $option['path'] ? 'border-fuchsia-500' : 'border-white/10 hover:border-white/30' }}">
                                    <img src="https://image.tmdb.org/t/p/w300{{ $option['path'] }}" alt="" class="w-full h-full object-cover">
                                    @if($option['dil'])
                                        <span class="absolute top-1 right-1 px-1 rounded bg-black/70 text-[9px] uppercase">{{ $option['dil'] }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-[11px] text-neutral-600">Dil rozetli olanların üzerinde yazı vardır, logoyla çakışabilir.</p>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium mb-2">Şerit rengi</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors {{ $accentMode === 'marka' ? 'border-fuchsia-500/60 bg-fuchsia-500/5' : 'border-white/5 hover:border-white/15' }}">
                            <input type="radio" wire:model.live="accentMode" value="marka" class="accent-fuchsia-500">
                            <span class="w-4 h-4 rounded shrink-0" style="background: {{ config('trailer.defaults.accent') }}"></span>
                            <span class="text-sm">Marka mavisi</span>
                        </label>
                        <label class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors {{ $accentMode === 'oto' ? 'border-fuchsia-500/60 bg-fuchsia-500/5' : 'border-white/5 hover:border-white/15' }}">
                            <input type="radio" wire:model.live="accentMode" value="oto" class="accent-fuchsia-500">
                            <span class="text-sm">Afişten otomatik</span>
                        </label>
                        <label class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors {{ $accentMode === 'ozel' ? 'border-fuchsia-500/60 bg-fuchsia-500/5' : 'border-white/5 hover:border-white/15' }}">
                            <input type="radio" wire:model.live="accentMode" value="ozel" class="accent-fuchsia-500">
                            <span class="text-sm">Özel renk</span>
                        </label>
                        @if($accentMode === 'ozel')
                            <div class="flex gap-2">
                                <input wire:model.blur="accent" type="text" placeholder="#00059e"
                                       class="flex-1 bg-neutral-950 border border-white/10 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:border-fuchsia-500/60">
                                <input wire:model.blur="accent" type="color" class="w-11 h-[38px] bg-neutral-950 border border-white/10 rounded-lg cursor-pointer">
                            </div>
                        @endif
                    </div>
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="brand" class="accent-fuchsia-500 w-4 h-4">
                    <span class="text-sm">Avşar Sinema logosu <span class="text-neutral-600">(alt köşe)</span></span>
                </label>

                @if($notice)
                    <p class="text-xs text-amber-400/90 border border-amber-500/20 bg-amber-500/5 rounded-lg px-3 py-2">{{ $notice }}</p>
                @endif
            </div>

            {{-- ---------------------------------------------------- Önizlemeler --}}
            <div id="kapaklar" class="xl:col-span-2 space-y-6 relative scroll-mt-24">

                <div wire:loading.flex wire:target="choose,build,chooseBackdrop,ribbonKey,logoMode,showMeta,accentMode,accent,brand,customRibbon"
                     class="absolute inset-0 z-10 bg-neutral-950/70 backdrop-blur-sm rounded-xl items-center justify-center">
                    <div class="flex items-center gap-3 text-sm text-neutral-300">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Kapaklar üretiliyor…
                    </div>
                </div>

                @forelse($thumbnails as $thumb)
                    <div class="bg-neutral-900 rounded-xl border border-white/5 overflow-hidden">
                        <div class="px-5 py-3 flex items-center justify-between border-b border-white/5">
                            <div>
                                <p class="font-semibold text-sm">{{ $thumb['label'] }}</p>
                                <p class="text-xs text-neutral-500 font-mono">{{ $thumb['key'] }} · 1280×720 · {{ $thumb['size'] }}</p>
                            </div>
                            <a href="{{ route('admin.trailers.preview', ['file' => $thumb['file'], 'indir' => 1]) }}"
                               class="px-4 py-2 text-sm rounded-lg bg-white/5 hover:bg-white/10 transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                İndir
                            </a>
                        </div>
                        <img src="{{ route('admin.trailers.preview', ['file' => $thumb['file'], 'v' => $renderedAt]) }}"
                             alt="{{ $thumb['label'] }}" class="w-full block">
                    </div>
                @empty
                    <div class="bg-neutral-900 rounded-xl border border-white/5 p-10 text-center text-neutral-500">
                        Bu yapım için kapak üretilemedi — TMDB'de yeterli görsel yok.
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
