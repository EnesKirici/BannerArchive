<?php

/**
 * Livewire QuoteGenerator Component (Embedded / Gomulu)
 *
 * YENI KAVRAMLAR:
 * ---------------
 * 1. `#[On('event-name')]` -> Baska component veya JS'den event dinler
 *    JS'den: Livewire.dispatch('open-quotes', { movie: {...}, bannerUrl: '...' })
 *    Bu metod otomatik cagrilir ve modal acilir
 *
 * 2. Alpine.js + Livewire entegrasyonu
 *    - x-on:click="navigator.clipboard.writeText(...)" -> Clipboard API (browser-only)
 *    - Livewire sunucu islemleri yapar, Alpine browser islemleri yapar
 *    - Ikisi ayni component icinde harmonik calisir
 *
 * 3. Cache::remember() / Cache::forget() -> Sunucu tarafinda cache yonetimi
 *    - Uretilen sozler 7 gun cache'lenir
 *    - Regenerate istendiginde cache temizlenir
 *    - Kullanici hic beklemeden cache'li sonuclari gorur
 *
 * 4. Livewire modal pattern:
 *    - $showModal property'si modal gorunurlugunu kontrol eder
 *    - Event gelince $showModal = true -> modal acilir
 *    - closeModal() cagrilinca $showModal = false -> modal kapanir
 *    - Sunucu-driven UI: modal durumu sunucuda tutulur
 *
 * 5. `$this->dispatch('event')->self()` -> Ayni component'a event gonderir
 *    - openQuotes() → modal'i acar (loading state) → dispatch('generate-quotes')->self()
 *    - generateQuotes() → API cagirisini AYRI bir request'te yapar
 *    - Bu sayede kullanici ONCE loading gorur, SONRA sonuclari gorur
 *    - Tek request'te yapilsaydi: 10sn bos ekran → sonuclar (kotu UX)
 */

use App\Services\QuoteGeneratorService;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    // ═══ MODAL STATE ═══
    public bool $showModal = false;

    // ═══ MOVIE DATA ═══
    /** @var array<string, mixed> */
    public array $movie = [];

    public string $bannerUrl = '';

    // ═══ QUOTES STATE ═══
    /** @var array<int, string> */
    public array $quotes = [];

    public bool $loading = false;

    public string $error = '';

    public string $style = '';

    public string $usedModel = '';

    // ═══ NAVIGATION STATE ═══
    // Arama sonuclari arasinda gezinme (onceki/sonraki film)
    /** @var array<int, array<string, mixed>> */
    public array $searchResults = [];

    public int $currentIndex = 0;

    /**
     * JS'den quotes modal acma istegi geldiginde calisir
     * Image modal'daki "AI Sozleri" butonundan tetiklenir:
     * Livewire.dispatch('open-quotes', { movie: {...}, bannerUrl: '...', results: [...] })
     *
     * @param  array<string, mixed>  $movie
     * @param  array<int, array<string, mixed>>  $results
     */
    #[On('open-quotes')]
    public function openQuotes(array $movie, string $bannerUrl = '', array $results = []): void
    {
        $this->movie = $movie;
        $this->bannerUrl = $bannerUrl;
        $this->searchResults = $results;
        $this->style = '';
        $this->error = '';

        // Sonuclar icinde mevcut filmin index'ini bul (prev/next icin)
        $this->currentIndex = 0;
        foreach ($this->searchResults as $i => $item) {
            if (($item['id'] ?? 0) === ($movie['id'] ?? -1)) {
                $this->currentIndex = $i;
                break;
            }
        }

        $this->showModal = true;
        $this->quotes = [];

        // Cache'de varsa direkt goster (aninda, API bekleme yok)
        $type = $movie['raw_type'] ?? 'movie';
        $id = $movie['id'] ?? 0;
        $cacheKey = "quotes_{$type}_{$id}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $this->quotes = $cached;
            $this->loading = false;

            return;
        }

        // Cache'de yok → loading state goster, SONRA ayri request'te API'yi cagir
        // dispatch()->self() → ayni component'a event gonderir, YENi bir HTTP request olarak
        // Bu sayede: 1. Modal acilir (loading gosterilir) → 2. API cagrilir → 3. Sonuclar gosterilir
        $this->loading = true;
        $this->dispatch('generate-quotes')->self();
    }

    /**
     * API'den soz uret — AYRI bir Livewire request'te calisir
     * dispatch()->self() ile tetiklenir, boylece loading state kullanici tarafindan gorulebilir
     */
    #[On('generate-quotes')]
    public function generateQuotes(): void
    {
        if (empty($this->movie)) {
            return;
        }

        $type = $this->movie['raw_type'] ?? 'movie';
        $id = $this->movie['id'] ?? 0;
        $cacheKey = "quotes_{$type}_{$id}";

        /** @var QuoteGeneratorService $service */
        $service = app(QuoteGeneratorService::class);

        $title = $this->movie['title'] ?? '';
        $overview = $this->movie['overview'] ?? '';

        $quotes = $service->generateQuotes($title, $overview, $type, $this->style);

        if (! empty($quotes)) {
            Cache::put($cacheKey, $quotes, now()->addDays(7));
            $this->quotes = $quotes;
            $this->usedModel = $service->getUsedModel();
        } else {
            $this->error = 'Sözler üretilemedi. Lütfen tekrar deneyin.';
        }

        $this->loading = false;
    }

    /**
     * Yeniden uret (opsiyonel stil ile)
     * wire:click="regenerate" ile cagrilir
     */
    public function regenerate(): void
    {
        $type = $this->movie['raw_type'] ?? 'movie';
        $id = $this->movie['id'] ?? 0;
        $cacheKey = "quotes_{$type}_{$id}";

        Cache::forget($cacheKey);

        $this->loading = true;
        $this->quotes = [];
        $this->error = '';

        // Ayni pattern: once loading goster, sonra API'yi cagir
        $this->dispatch('generate-quotes')->self();
    }

    /**
     * Onceki/sonraki filme gec (search results icinde)
     * wire:click="navigateMovie(-1)" veya wire:click="navigateMovie(1)" ile cagrilir
     */
    public function navigateMovie(int $direction): void
    {
        if (empty($this->searchResults)) {
            return;
        }

        $newIndex = $this->currentIndex + $direction;

        if ($newIndex < 0 || $newIndex >= count($this->searchResults)) {
            return;
        }

        $this->currentIndex = $newIndex;
        $movie = $this->searchResults[$newIndex];
        $this->movie = $movie;
        $this->style = '';
        $this->error = '';
        $this->quotes = [];
        $this->bannerUrl = 'https://image.tmdb.org/t/p/w780' . ($movie['backdrop_path'] ?? '');

        // Cache'de varsa direkt goster
        $type = $movie['raw_type'] ?? 'movie';
        $id = $movie['id'] ?? 0;
        $cacheKey = "quotes_{$type}_{$id}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $this->quotes = $cached;
            $this->loading = false;

            return;
        }

        // Cache yok → loading + API
        $this->loading = true;
        $this->dispatch('generate-quotes')->self();
    }

    /**
     * Modal'i kapat
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->loading = false;
    }
};
?>

{{-- ═══════════════════════════════════════════════════════════════
     QUOTES MODAL TEMPLATE
     $showModal true oldugunda gorunur.
     Alpine.js ile animasyon ve clipboard islemleri yapilir.
     ═══════════════════════════════════════════════════════════════ --}}

<div>
{{-- Modal sadece $showModal = true ise renderlanir --}}
@if ($showModal)
<div class="fixed inset-0 z-70"
     x-data="{ open: false, copied: null }"
     x-init="$nextTick(() => open = true)"
     x-on:keydown.escape.window="$wire.closeModal()">

    {{-- Backdrop (arka plan karartmasi) --}}
    <div class="absolute inset-0 bg-black/95 backdrop-blur-md transition-opacity duration-300"
         x-bind:class="open ? 'opacity-100' : 'opacity-0'"
         x-on:click="$wire.closeModal()"></div>

    {{-- Modal Container --}}
    <div class="absolute inset-0 flex items-center justify-center p-4 md:p-8 pointer-events-none">
        <div class="w-full max-w-5xl bg-neutral-900 rounded-2xl overflow-hidden shadow-2xl border border-white/10 flex flex-col max-h-[90vh] pointer-events-auto transform transition-all duration-300"
             x-bind:class="open ? 'scale-100 opacity-100' : 'scale-95 opacity-0'">

            {{-- ═══ HEADER ═══ --}}
            <div class="p-4 md:p-6 border-b border-white/5 flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-white flex items-center gap-2">
                        {{ $movie['title'] ?? '' }}
                        <span class="text-xs font-normal text-neutral-500">- AI Banner Sözleri</span>
                    </h2>
                    <p class="text-xs text-neutral-500 mt-1">
                        {{ $movie['type'] ?? '' }} &middot; {{ isset($movie['release_date']) && $movie['release_date'] ? substr($movie['release_date'], 0, 4) : '' }}
                        @if ($usedModel)
                            &middot; <span class="text-fuchsia-500/70">{{ $usedModel }}</span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Film navigasyonu (onceki / sonraki) --}}
                    @if (count($searchResults) > 1)
                        <button wire:click="navigateMovie(-1)"
                                @disabled($currentIndex <= 0)
                                class="cursor-pointer p-2 bg-neutral-800 rounded-full hover:bg-neutral-700 text-white transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <span class="text-xs text-neutral-500 font-mono">{{ $currentIndex + 1 }}/{{ count($searchResults) }}</span>
                        <button wire:click="navigateMovie(1)"
                                @disabled($currentIndex >= count($searchResults) - 1)
                                class="cursor-pointer p-2 bg-neutral-800 rounded-full hover:bg-neutral-700 text-white transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    @endif
                    {{-- Kapat butonu --}}
                    <button wire:click="closeModal"
                            class="cursor-pointer p-2 bg-neutral-800 rounded-full hover:bg-fuchsia-600 text-white transition-colors shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            {{-- ═══ CONTENT ═══ --}}
            <div class="flex-1 flex flex-col md:flex-row overflow-hidden min-h-0">

                {{-- Sol Panel: Banner Gorseli --}}
                <div class="md:w-2/5 shrink-0 bg-black/50 flex items-center justify-center p-4">
                    @if ($bannerUrl)
                        <img src="{{ $bannerUrl }}" alt="{{ $movie['title'] ?? '' }}"
                             class="max-w-full max-h-[50vh] object-contain rounded-lg">
                    @else
                        <div class="text-neutral-600 text-sm">Görsel yok</div>
                    @endif
                </div>

                {{-- Sag Panel: Sozler --}}
                <div class="flex-1 flex flex-col overflow-hidden border-l border-white/5">

                    {{-- ═══ LOADING STATE ═══
                         wire:loading yerine $loading property kullaniyoruz
                         cunku API istegi senkron (ayni request icinde) --}}
                    @if ($loading)
                        <div class="flex-1 flex flex-col items-center justify-center gap-4 p-8">
                            <div class="w-12 h-12 border-4 border-neutral-800 border-t-fuchsia-500 rounded-full animate-spin"></div>
                            <p class="text-neutral-400 text-sm">AI sözler üretiyor...</p>
                            <p class="text-neutral-600 text-xs">Bu işlem birkaç saniye sürebilir</p>
                        </div>

                    {{-- ═══ ERROR STATE ═══ --}}
                    @elseif ($error !== '')
                        <div class="flex-1 flex flex-col items-center justify-center gap-4 p-8">
                            <svg class="w-12 h-12 text-red-500/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                            <p class="text-red-400 text-sm">{{ $error }}</p>
                            <button wire:click="regenerate"
                                    class="cursor-pointer px-4 py-2 bg-fuchsia-600 hover:bg-fuchsia-500 text-white text-sm font-bold rounded-lg transition-colors">
                                Tekrar Dene
                            </button>
                        </div>

                    {{-- ═══ SUCCESS STATE ═══ --}}
                    @else
                        <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-hide">
                            @foreach ($quotes as $index => $quote)
                                {{-- Her soz karti
                                     Alpine.js ile clipboard islemleri:
                                     - x-on:click → Karta tikla → metni kopyala
                                     - copied state → Hangi kartın kopyalandigini takip eder
                                     - Livewire bunu YAPAMAZ cunku clipboard browser API'dir --}}
                                <div wire:key="quote-{{ $index }}"
                                     x-on:click="
                                        navigator.clipboard.writeText({{ Js::from($quote) }}).then(() => {
                                            copied = {{ $index }};
                                            setTimeout(() => { if(copied === {{ $index }}) copied = null }, 1500)
                                        })
                                     "
                                     class="group/card relative p-4 bg-neutral-800/50 hover:bg-neutral-800 rounded-xl border border-white/5 hover:border-fuchsia-500/30 cursor-pointer transition-all">

                                    <div class="flex items-start gap-3">
                                        {{-- Soz numarasi --}}
                                        <span class="shrink-0 w-6 h-6 rounded-full bg-neutral-700 flex items-center justify-center text-xs font-bold text-neutral-400">
                                            {{ $index + 1 }}
                                        </span>

                                        {{-- Soz metni --}}
                                        <p class="flex-1 text-sm text-neutral-200 leading-relaxed italic">
                                            "{{ $quote }}"
                                        </p>

                                        {{-- Kopyala ikonu / Kopyalandi geri bildirimi --}}
                                        <div class="shrink-0 p-1.5 rounded-lg opacity-0 group-hover/card:opacity-100 transition-opacity"
                                             x-bind:class="copied === {{ $index }} ? 'opacity-100 bg-green-600/50' : ''">
                                            <template x-if="copied !== {{ $index }}">
                                                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                            </template>
                                            <template x-if="copied === {{ $index }}">
                                                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- ═══ FOOTER: Stil + Aksiyonlar ═══ --}}
                        <div class="p-4 border-t border-white/5 shrink-0 space-y-3">
                            {{-- Stil girisi + Yeniden uret --}}
                            <div class="flex gap-2">
                                <input type="text"
                                       wire:model="style"
                                       wire:keydown.enter="regenerate"
                                       placeholder="Stil belirt (örnek: epik, romantik, karanlık...)"
                                       class="flex-1 px-3 py-2 bg-neutral-800 border border-white/10 rounded-lg text-sm text-white placeholder-neutral-500 focus:outline-none focus:border-fuchsia-500 transition-colors">
                                <button wire:click="regenerate"
                                        wire:loading.attr="disabled"
                                        class="cursor-pointer px-4 py-2 bg-fuchsia-600 hover:bg-fuchsia-500 disabled:opacity-50 text-white text-sm font-bold rounded-lg transition-colors flex items-center gap-2 whitespace-nowrap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    <span wire:loading.remove wire:target="regenerate">Yeniden Üret</span>
                                    <span wire:loading wire:target="regenerate">Üretiyor...</span>
                                </button>
                            </div>

                            {{-- Tumunu Kopyala + Gorseli Indir --}}
                            <div class="flex gap-2">
                                {{-- Tumunu Kopyala: Alpine.js ile clipboard --}}
                                <button x-data="{ allCopied: false }"
                                        x-on:click="
                                            const allText = {{ Js::from($quotes) }}.map((q, i) => (i+1) + '. ' + q).join('\n');
                                            navigator.clipboard.writeText(allText).then(() => {
                                                allCopied = true;
                                                setTimeout(() => allCopied = false, 1500)
                                            })
                                        "
                                        class="cursor-pointer flex-1 py-2.5 bg-neutral-800 hover:bg-neutral-700 text-white text-sm font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
                                    <template x-if="!allCopied">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                            Tümünü Kopyala ({{ count($quotes) }})
                                        </span>
                                    </template>
                                    <template x-if="allCopied">
                                        <span class="flex items-center gap-2 text-green-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            Kopyalandı!
                                        </span>
                                    </template>
                                </button>

                                {{-- Gorseli Indir: Browser event dispatch -> JS handler --}}
                                <button x-on:click="$dispatch('download-from-quotes', { movie: {{ Js::from($movie) }} })"
                                        class="cursor-pointer flex-1 py-2.5 bg-white text-black hover:bg-fuchsia-500 hover:text-white text-sm font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Görseli İndir
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif
</div>
