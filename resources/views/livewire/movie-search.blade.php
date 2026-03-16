<?php

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public string $filter = 'all'; // all, movie, tv

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public function updatedSearch(): void
    {
        if (mb_strlen($this->search) < 2) {
            $this->results = [];

            return;
        }

        $this->performSearch();

        $this->dispatch('results-updated', results: $this->results);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    #[On('select-from-sidebar')]
    public function selectFromSidebar(array $movie): void
    {
        $this->results = [$movie];
        $this->search = $movie['title'];
        $this->filter = $movie['raw_type'];

        $this->dispatch('results-updated', results: $this->results);
    }

    public function selectSuggestion(int $index): void
    {
        $suggestions = array_slice($this->results, 0, 5);

        if (! isset($suggestions[$index])) {
            return;
        }

        $this->search = $suggestions[$index]['title'];
        $this->performSearch();
        $this->dispatch('results-updated', results: $this->results);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredResults(): array
    {
        if ($this->filter === 'all') {
            return $this->results;
        }

        return array_values(array_filter(
            $this->results,
            fn (array $item): bool => $item['raw_type'] === $this->filter
        ));
    }

    private function performSearch(): void
    {
        $apiKey = config('services.tmdb.api_key');
        $baseUrl = config('services.tmdb.base_url');

        $response = Http::get("{$baseUrl}/search/multi", [
            'api_key' => $apiKey,
            'query' => $this->search,
            'language' => 'tr-TR',
            'include_adult' => false,
        ]);

        if ($response->successful()) {
            $results = $response->json()['results'] ?? [];

            $results = array_filter($results, fn (array $item): bool => ! empty($item['backdrop_path']) && ($item['media_type'] ?? '') !== 'person'
            );

            $this->results = array_values(
                array_map(fn (array $item): array => $this->formatItem($item, $item['media_type']), $results)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function formatItem(array $item, string $type): array
    {
        $isMovie = $type === 'movie';

        return [
            'id' => $item['id'],
            'title' => $isMovie ? ($item['title'] ?? '') : ($item['name'] ?? ''),
            'overview' => $item['overview'] ?? '',
            'poster_path' => $item['poster_path'] ?? '',
            'backdrop_path' => $item['backdrop_path'] ?? '',
            'vote_average' => $item['vote_average'] ?? 0,
            'release_date' => $isMovie ? ($item['release_date'] ?? null) : ($item['first_air_date'] ?? null),
            'type' => $isMovie ? 'Film' : 'Dizi',
            'raw_type' => $isMovie ? 'movie' : 'tv',
        ];
    }
};
?>

<div x-data="{ highlight: -1, open: false }">
    {{-- ═══ SEARCH BAR ═══ --}}
    <div class="relative w-full max-w-2xl mx-auto z-20"
         x-on:click.outside="open = false; highlight = -1">
        <div class="relative flex items-center bg-black/80 backdrop-blur-sm border border-neutral-800 rounded-full shadow-2xl overflow-hidden transition-all duration-300 focus-within:border-fuchsia-500 focus-within:shadow-[0_0_30px_rgba(217,70,239,0.3)]">
            <div class="pl-6 text-neutral-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.500ms="search"
                   class="w-full bg-transparent text-white p-4 pl-4 focus:outline-none placeholder-neutral-500 text-lg"
                   placeholder="Search movies or TV shows..."
                   autocomplete="off"
                   @keydown.arrow-down.prevent="if (!open && $wire.results.length > 0) { open = true; highlight = 0 } else if (open) { highlight = Math.min(highlight + 1, {{ max(min(count($results), 5) - 1, 0) }}) }"
                   @keydown.arrow-up.prevent="if (open) { highlight = Math.max(highlight - 1, -1); if (highlight < 0) open = false }"
                   @keydown.enter.prevent="if (open && highlight >= 0) { $wire.selectSuggestion(highlight); open = false; highlight = -1 }"
                   @keydown.escape="open = false; highlight = -1">

            <div wire:loading wire:target="search" class="pr-4">
                <div class="w-5 h-5 border-2 border-neutral-600 border-t-fuchsia-500 rounded-full animate-spin"></div>
            </div>
        </div>

        {{-- ═══ AUTOCOMPLETE SUGGESTIONS (Arrow Down ile acilir) ═══ --}}
        @if (! empty($results))
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute top-full left-0 right-0 mt-1 bg-neutral-950/95 backdrop-blur-lg border border-neutral-800 rounded-2xl overflow-hidden shadow-2xl z-50">
                @foreach (array_slice($results, 0, 5) as $index => $suggestion)
                    <button wire:click="selectSuggestion({{ $index }})"
                            wire:key="suggestion-{{ $suggestion['id'] }}"
                            @click="open = false; highlight = -1"
                            :class="highlight === {{ $index }} && 'bg-fuchsia-500/15'"
                            class="w-full flex items-center gap-2.5 px-4 py-2 hover:bg-fuchsia-500/10 transition-colors text-left border-b border-white/5 last:border-b-0"
                            @mouseenter="highlight = {{ $index }}">
                        @if ($suggestion['poster_path'])
                            <img src="https://image.tmdb.org/t/p/w92{{ $suggestion['poster_path'] }}"
                                 class="w-6 h-9 object-cover rounded shrink-0"
                                 loading="lazy" alt="">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-white/90 text-sm font-medium truncate">{{ $suggestion['title'] }}</p>
                        </div>
                        <span class="text-[10px] text-fuchsia-400/70 shrink-0">{{ $suggestion['type'] }} @if ($suggestion['release_date'])&middot; {{ substr($suggestion['release_date'], 0, 4) }}@endif</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══ FILTER BUTTONS ═══ --}}
    <div class="flex items-center justify-center gap-2 mt-6">
        @foreach (['all' => 'All', 'movie' => 'Movies', 'tv' => 'TV Shows'] as $value => $label)
            <button wire:click="setFilter('{{ $value }}')"
                    class="cursor-pointer px-4 py-1.5 rounded-full text-xs font-bold transition-all
                        {{ $filter === $value
                            ? 'bg-white text-black shadow-[0_0_15px_rgba(255,255,255,0.3)]'
                            : 'bg-neutral-800/80 backdrop-blur-sm text-white hover:bg-neutral-700 border border-white/10' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ═══ CONTENT AREA ═══ --}}
    <div class="w-full max-w-[1920px] mx-auto mt-10">

        <div wire:loading wire:target="search" class="py-12 text-center">
            <div class="inline-block w-10 h-10 border-4 border-neutral-800 border-t-fuchsia-500 rounded-full animate-spin"></div>
        </div>

        @if ($search === '')
            <div class="text-center py-20 opacity-30 pointer-events-none">
                <p class="text-neutral-600 text-sm">Search for high-resolution banners</p>
            </div>
        @endif

        @if ($search !== '' && empty($this->getFilteredResults()))
            <div wire:loading.remove wire:target="search" class="text-center py-12">
                @if (! empty($results) && $filter !== 'all')
                    <p class="text-neutral-500">Bu kategoride sonuc bulunamadi.</p>
                @else
                    <p class="text-neutral-500">Sonuc bulunamadi.</p>
                @endif
            </div>
        @endif

        <div wire:loading.remove wire:target="search"
             class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6">
            @foreach ($this->getFilteredResults() as $movie)
                <div wire:key="movie-{{ $movie['id'] }}"
                     x-data="{ dims: '...' }"
                     x-on:click="$dispatch('open-image-modal', { movie: {{ Js::from($movie) }} })"
                     class="group relative bg-neutral-900 rounded-xl overflow-hidden cursor-pointer border border-neutral-800 hover:border-fuchsia-500/50 transition-all duration-300 hover:shadow-[0_0_30px_rgba(217,70,239,0.15)] hover:-translate-y-1 flex flex-col">

                    <div class="aspect-video w-full overflow-hidden bg-neutral-950 relative">
                        <img src="https://image.tmdb.org/t/p/w780{{ $movie['backdrop_path'] }}"
                             alt="{{ $movie['title'] }}"
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                             loading="lazy"
                             x-on:load="dims = $el.naturalWidth + ' x ' + $el.naturalHeight">

                        <div class="absolute top-2 right-2 bg-black/60 backdrop-blur-md px-2 py-1 rounded text-[10px] font-bold text-white border border-white/10">
                            {{ $movie['type'] }}
                        </div>
                    </div>

                    <div class="p-4 flex flex-col gap-2">
                        <h3 class="text-white font-bold text-base leading-tight truncate group-hover:text-fuchsia-400 transition-colors">
                            {{ $movie['title'] }}
                        </h3>
                        <div class="flex items-center justify-between text-xs text-neutral-500 mt-auto">
                            <div class="flex items-center gap-1.5">
                                <span class="text-fuchsia-400/80 text-[10px] font-medium">{{ $movie['type'] }}</span>
                                @if ($movie['vote_average'] > 0)
                                    <span class="text-neutral-600">&middot;</span>
                                    <span class="text-yellow-500 text-[10px]">&#9733; {{ number_format($movie['vote_average'], 1) }}</span>
                                @endif
                                <span x-text="dims !== '...' ? '· ' + dims : ''"
                                      class="text-fuchsia-500/60 font-mono text-[10px]"></span>
                            </div>
                            <span>{{ $movie['release_date'] ? substr($movie['release_date'], 0, 4) : '' }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
