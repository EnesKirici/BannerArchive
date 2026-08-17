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
use Livewire\Attributes\Session;
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

    /**
     * Kapak biçimi: video (16:9) · shorts (9:16 dikey) · both (ikisi de).
     * null = içerik seçildi ama biçim henüz sorulmadı; kapak üretilmez.
     * Her içerik seçiminde sıfırlanır — kullanıcıya her seferinde sorulur.
     */
    public ?string $format = null;

    /*
    | Aşağıdaki tercihler #[Session] ile saklanıyor: bir kez seçilen ayar
    | sonraki filmlerde ve sonraki girişlerde aynı kalsın diye. Filme özgü
    | olanlar (seçilen arka plan/logo) bilinçli olarak saklanmıyor.
    */

    #[Session]
    public string $ribbonKey = 'altyazi';

    #[Session]
    public string $customRibbon = '';

    /** auto = TMDB ne verdiyse · tr = sadece Türkçe logo · text = logo yerine başlık */
    #[Session]
    public string $logoMode = 'auto';

    #[Session]
    public bool $showMeta = true;

    /** marka = sabit kurumsal renk · oto = afişten çıkar · ozel = elle seçilen */
    #[Session]
    public string $accentMode = 'marka';

    #[Session]
    public string $accent = '';

    #[Session]
    public bool $brand = true;

    /** renkli = kendi rengi (koyuysa beyaz zeminli) · beyaz = logo beyaza boyanır */
    #[Session]
    public string $brandStyle = 'renkli';

    /** Elle seçilen arka plan (TMDB dosya yolu); null = en iyisi otomatik seçilsin. */
    public ?string $backdropChoice = null;

    /** @var array<int, array{path: string, dil: string|null}> */
    public array $backdropOptions = [];

    /** Elle seçilen film adı logosu; null = "Film adı nasıl yazılsın" kuralları geçerli. */
    public ?string $logoChoice = null;

    /** @var array<int, array{path: string, dil: string|null}> */
    public array $logoOptions = [];

    public function mount(): void
    {
        // Oturumdan gelen bir tercih varsa ona dokunma; yoksa yapılandırmadan doldur.
        if ($this->accent === '') {
            $this->accent = (string) config('trailer.defaults.accent', '#00059e');
        }
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
        $this->logoChoice = null;
        $this->format = null;
        $this->thumbnails = [];
        $this->artwork = [];
        $this->notice = null;
        $this->error = null;

        $artwork = app(ArtworkFetcher::class);
        $this->backdropOptions = $artwork->backdrops($this->selectedType, $id);
        $this->logoOptions = $artwork->logos($this->selectedType, $id);

        // Kapak hemen üretilmez: önce biçim (video/shorts) sorulur. Kullanıcı
        // seçim yaparken görseller ayrı bir istekte arka planda indirilir;
        // biçime basıldığında her şey diskte hazır olur, üretim hızlı biter.
        $this->js('$wire.prefetchArtwork()');

        $this->js('setTimeout(() => document.getElementById("kapak-turu")?.scrollIntoView({ behavior: "smooth", block: "center" }), 80)');
    }

    /**
     * Kullanıcı biçim seçerken TMDB görsellerini diske indir (arka plan isteği).
     *
     * Yalnızca indirme yapar, kapak üretmez; aynı görseller build() sırasında
     * tekrar istendiğinde diskten okunur ve ağa hiç çıkılmaz.
     */
    public function prefetchArtwork(ArtworkFetcher $artwork): void
    {
        if ($this->selectedId === null) {
            return;
        }

        $payload = $artwork->fetch($this->selectedType, $this->selectedId);

        if ($payload === null) {
            return;
        }

        // Rozet satırı erkenden dolsun: hangi görseller var, seçim ekranında görünür.
        $this->artwork = [
            'afiş' => $payload->poster !== null,
            'backdrop' => $payload->backdrop !== null,
            'logo' => $payload->logo !== null,
        ];
    }

    public function chooseFormat(string $format): void
    {
        if (! in_array($format, ['video', 'shorts', 'both'], true)) {
            return;
        }

        $this->format = $format;

        $this->build(app(ArtworkFetcher::class), app(ThumbnailComposer::class));

        // Kapaklar sayfanın altında üretiliyor; kullanıcıyı oraya götür.
        // Kısa gecikme, hedef bölümün DOM'a yerleşmesini bekliyor.
        $this->js('setTimeout(() => document.getElementById("kapaklar")?.scrollIntoView({ behavior: "smooth", block: "start" }), 80)');
    }

    public function chooseBackdrop(?string $path): void
    {
        $this->backdropChoice = $path;

        $this->build(app(ArtworkFetcher::class), app(ThumbnailComposer::class));
    }

    public function chooseLogo(?string $path): void
    {
        $this->logoChoice = $path;

        $this->build(app(ArtworkFetcher::class), app(ThumbnailComposer::class));
    }

    /** Ayarlardan biri değişince kapakları tazele. */
    public function updated(string $property, mixed $value = null): void
    {
        if ($this->selectedId === null || $this->format === null || $property === 'query') {
            return;
        }

        $this->build(app(ArtworkFetcher::class), app(ThumbnailComposer::class));
    }

    public function build(ArtworkFetcher $artwork, ThumbnailComposer $composer): void
    {
        $this->error = null;
        $this->notice = null;
        $this->thumbnails = [];

        if ($this->selectedId === null || $this->format === null) {
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

        // Logo da elle seçilebiliyor; seçildiyse "film adı nasıl yazılsın"
        // kuralları devreye girmez, doğrudan seçilen kullanılır.
        if ($this->logoChoice !== null) {
            $payload = $payload->withLogo(
                $artwork->download($this->logoChoice, (string) config('trailer.sizes.logo'))
            );

            $chosenLogo = collect($this->logoOptions)->firstWhere('path', $this->logoChoice);
            $this->logoLanguage = is_array($chosenLogo) ? ($chosenLogo['dil'] ?? null) : null;
        } else {
            $this->logoLanguage = $payload->logoLanguage;
        }

        $this->artwork = [
            'afiş' => $payload->poster !== null,
            'backdrop' => $payload->backdrop !== null,
            'logo' => $payload->logo !== null,
        ];

        $payload = $payload->with(
            ribbon: $this->ribbonText(),
            brand: $this->brand,
            brandWhite: $this->brandStyle === 'beyaz',
        );

        $payload = match ($this->accentMode) {
            'oto' => $payload->withoutAccent(),
            'ozel' => $payload->with(accent: $this->accent),
            default => $payload->with(accent: (string) config('trailer.defaults.accent')),
        };

        if (! $this->showMeta) {
            $payload = $payload->withoutMeta();
        }

        if ($this->logoChoice !== null) {
            $this->notice = 'Logo elle seçildi — otomatik kurallar uygulanmadı.';
        } elseif ($this->logoMode === 'text') {
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

        $formats = match ($this->format) {
            'both' => ['video', 'shorts'],
            default => [$this->format],
        };

        try {
            // renderAll: vurgu rengini tüm şablonlar için bir kez hesaplar.
            $rendered = $composer->renderAll($payload, $directory, $formats);
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = 'Kapak üretilemedi: '.$exception->getMessage();

            return;
        }

        $labels = $composer->options();
        $templateFormats = $composer->formats();

        $this->thumbnails = collect($rendered)
            ->map(fn (string $path, string $key): array => [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'format' => $templateFormats[$key] ?? 'video',
                'boyut' => ($templateFormats[$key] ?? 'video') === 'shorts'
                    ? config('trailer.shorts.width').'×'.config('trailer.shorts.height')
                    : config('trailer.thumbnail.width').'×'.config('trailer.thumbnail.height'),
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

    {{-- ------------------------------------------- Kapak türü (video / shorts) --}}
    @if($selectedId)
        <div id="kapak-turu" class="bg-neutral-900 rounded-xl border border-white/5 p-6 scroll-mt-24">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <h3 class="font-semibold">
                        {{ $selectedTitle }}
                        <span class="text-neutral-500 font-normal">— hangi kapak üretilsin?</span>
                    </h3>
                    <p class="mt-1 text-xs text-neutral-500">
                        <span wire:loading wire:target="prefetchArtwork" class="inline-flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Siz seçerken görseller arka planda indiriliyor…
                        </span>
                        <span wire:loading.remove wire:target="prefetchArtwork">
                            {{ $artwork !== [] ? 'Görseller hazır — türü seçince kapaklar üretilir.' : 'Kapak türünü seçin.' }}
                        </span>
                    </p>
                </div>
                @if($artwork)
                    <div class="flex flex-wrap gap-2">
                        @foreach($artwork as $name => $found)
                            <span class="px-2 py-0.5 text-[11px] rounded {{ $found ? 'bg-emerald-500/10 text-emerald-400' : 'bg-neutral-800 text-neutral-500' }}">
                                {{ $name }} {{ $found ? '✓' : '✗' }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach([
                    'video' => ['Video Kapağı', '16:9 · '.config('trailer.thumbnail.width').'×'.config('trailer.thumbnail.height'), 'M3 6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 17.25V6.75z'],
                    'shorts' => ['Shorts Kapağı', '9:16 dikey · '.config('trailer.shorts.width').'×'.config('trailer.shorts.height'), 'M8.25 3h7.5A2.25 2.25 0 0118 5.25v13.5A2.25 2.25 0 0115.75 21h-7.5A2.25 2.25 0 016 18.75V5.25A2.25 2.25 0 018.25 3z'],
                    'both' => ['İkisi de', 'Video + Shorts birlikte üretilir', 'M3 8.25A2.25 2.25 0 015.25 6h8.5A2.25 2.25 0 0116 8.25v7.5A2.25 2.25 0 0113.75 18h-8.5A2.25 2.25 0 013 15.75v-7.5zM18.5 6.5h.25A2.25 2.25 0 0121 8.75v6.5a2.25 2.25 0 01-2.25 2.25h-.25'],
                ] as $key => [$baslik, $aciklama, $ikon])
                    <button type="button" wire:click="chooseFormat('{{ $key }}')"
                            wire:loading.attr="disabled" wire:target="chooseFormat"
                            class="group text-left px-4 py-4 rounded-xl border transition-colors disabled:opacity-60 {{ $format === $key ? 'border-fuchsia-500 bg-fuchsia-500/10' : 'border-white/10 hover:border-fuchsia-500/50 hover:bg-white/5' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-7 h-7 shrink-0 {{ $format === $key ? 'text-fuchsia-400' : 'text-neutral-400 group-hover:text-fuchsia-400' }} transition-colors"
                                 fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}"/>
                            </svg>
                            <span>
                                <span class="block font-semibold text-sm">{{ $baslik }}</span>
                                <span class="block text-[11px] text-neutral-500">{{ $aciklama }}</span>
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div wire:loading.flex wire:target="chooseFormat" class="mt-3 items-center gap-2 text-sm text-neutral-300">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Kapaklar üretiliyor…
            </div>
        </div>
    @endif

    @if($selectedId && $format)
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
                        <p class="mt-2 text-xs text-neutral-500">
                            Kullanılan logo dili: <span class="font-mono uppercase">{{ $logoLanguage }}</span>
                        </p>
                    @endif

                    {{-- TMDB'nin seçtiği logo kötüyse elle seçim. Liste uzun olduğu
                         için kapalı başlar, Alpine ile açılır. --}}
                    @if($logoOptions)
                        <div x-data="{ acik: false }" class="mt-3">
                            <button type="button" x-on:click="acik = !acik"
                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg border border-white/5 hover:border-white/15 transition-colors text-left">
                                <span class="text-sm">
                                    Logoyu kendim seçeyim
                                    <span class="text-neutral-600">({{ count($logoOptions) }})</span>
                                </span>
                                <svg class="w-4 h-4 text-neutral-500 transition-transform" x-bind:class="acik ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div x-show="acik" style="display: none" x-transition
                                 class="mt-2 grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
                                <button type="button" wire:click="chooseLogo(null)"
                                        class="h-16 rounded-md border text-[11px] flex items-center justify-center transition-colors {{ $logoChoice === null ? 'border-fuchsia-500 text-white' : 'border-white/10 text-neutral-400 hover:border-white/30' }}">
                                    Otomatik
                                </button>
                                @foreach($logoOptions as $option)
                                    {{-- Zemin gradyanlı: hem beyaz hem koyu logolar görünsün --}}
                                    <button type="button" wire:click="chooseLogo('{{ $option['path'] }}')" wire:key="logo-{{ $loop->index }}"
                                            class="relative h-16 rounded-md border p-2 flex items-center justify-center bg-gradient-to-br from-neutral-400 to-neutral-800 transition-colors {{ $logoChoice === $option['path'] ? 'border-fuchsia-500' : 'border-white/10 hover:border-white/30' }}">
                                        <img src="https://image.tmdb.org/t/p/w300{{ $option['path'] }}" alt="" class="max-h-full max-w-full object-contain">
                                        @if($option['dil'])
                                            <span class="absolute top-1 right-1 px-1 rounded bg-black/70 text-[9px] uppercase text-white">{{ $option['dil'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="showMeta" class="accent-fuchsia-500 w-4 h-4">
                    <span class="text-sm">Yıl • tür satırını göster</span>
                </label>

                @if($backdropOptions)
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            Arka plan görseli
                            <span class="text-neutral-600 font-normal">({{ count($backdropOptions) }} görsel)</span>
                        </label>
                        <div class="grid grid-cols-3 gap-2 max-h-96 overflow-y-auto pr-1">
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

                <div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model.live="brand" class="accent-fuchsia-500 w-4 h-4">
                        <span class="text-sm">Avşar Sinema logosu <span class="text-neutral-600">(alt köşe)</span></span>
                    </label>

                    @if($brand)
                        <div class="mt-2 space-y-2">
                            @foreach([
                                'renkli' => ['Kendi rengi', 'koyu kapakta beyaz zemin üstünde'],
                                'beyaz' => ['Beyaz logo', 'zeminsiz, doğrudan kapağın üstünde'],
                            ] as $key => [$baslik, $aciklama])
                                <label class="flex items-start gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors {{ $brandStyle === $key ? 'border-fuchsia-500/60 bg-fuchsia-500/5' : 'border-white/5 hover:border-white/15' }}">
                                    <input type="radio" wire:model.live="brandStyle" value="{{ $key }}" class="accent-fuchsia-500 mt-0.5">
                                    <span>
                                        <span class="block text-sm">{{ $baslik }}</span>
                                        <span class="block text-[11px] text-neutral-500">{{ $aciklama }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($notice)
                    <p class="text-xs text-amber-400/90 border border-amber-500/20 bg-amber-500/5 rounded-lg px-3 py-2">{{ $notice }}</p>
                @endif
            </div>

            {{-- ---------------------------------------------------- Önizlemeler --}}
            <div id="kapaklar" class="xl:col-span-2 space-y-6 relative scroll-mt-24">

                <div wire:loading.flex wire:target="chooseFormat,build,chooseBackdrop,chooseLogo,ribbonKey,logoMode,showMeta,accentMode,accent,brand,brandStyle,customRibbon"
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
                    <div class="bg-neutral-900 rounded-xl border border-white/5 overflow-hidden" wire:key="kapak-{{ $thumb['key'] }}">
                        <div class="px-5 py-3 flex items-center justify-between border-b border-white/5">
                            <div>
                                <p class="font-semibold text-sm flex items-center gap-2">
                                    {{ $thumb['label'] }}
                                    <span class="px-1.5 py-0.5 text-[10px] rounded uppercase tracking-wide {{ $thumb['format'] === 'shorts' ? 'bg-rose-500/10 text-rose-400' : 'bg-sky-500/10 text-sky-400' }}">
                                        {{ $thumb['format'] === 'shorts' ? 'Shorts' : 'Video' }}
                                    </span>
                                </p>
                                <p class="text-xs text-neutral-500 font-mono">{{ $thumb['key'] }} · {{ $thumb['boyut'] }} · {{ $thumb['size'] }}</p>
                            </div>
                            <a href="{{ route('admin.trailers.preview', ['file' => $thumb['file'], 'indir' => 1]) }}"
                               class="px-4 py-2 text-sm rounded-lg bg-white/5 hover:bg-white/10 transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                İndir
                            </a>
                        </div>
                        @if($thumb['format'] === 'shorts')
                            {{-- Dikey kapak telefonda görüneceği gibi: dar, çerçeveli önizleme --}}
                            <div class="p-6 flex justify-center bg-neutral-950/50">
                                <img src="{{ route('admin.trailers.preview', ['file' => $thumb['file'], 'v' => $renderedAt]) }}"
                                     alt="{{ $thumb['label'] }}" class="w-full max-w-[300px] rounded-2xl border border-white/10 shadow-2xl">
                            </div>
                        @else
                            <img src="{{ route('admin.trailers.preview', ['file' => $thumb['file'], 'v' => $renderedAt]) }}"
                                 alt="{{ $thumb['label'] }}" class="w-full block">
                        @endif
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
