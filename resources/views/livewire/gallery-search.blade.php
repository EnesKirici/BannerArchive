<?php

use App\Services\TmdbClient;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public function updatedSearch(): void
    {
        if (mb_strlen($this->search) < 2) {
            $this->results = [];

            return;
        }

        $this->performSearch();
    }

    public function goToGallery(string $type, int $id): void
    {
        if (! in_array($type, ['movie', 'tv'], true)) {
            return;
        }

        $this->redirect(route('gallery', ['type' => $type, 'id' => $id]));
    }

    private function performSearch(): void
    {
        $data = app(TmdbClient::class)->get('/search/multi', [
            'query' => $this->search,
            'language' => 'tr-TR',
            'include_adult' => false,
        ]);

        if ($data === null) {
            return;
        }

        $items = array_filter(
            $data['results'] ?? [],
            fn (array $item): bool => in_array($item['media_type'] ?? '', ['movie', 'tv'], true) && ! empty($item['poster_path'])
        );

        $this->results = array_values(array_map(function (array $item): array {
            $isMovie = ($item['media_type'] ?? '') === 'movie';

            return [
                'id' => $item['id'],
                'title' => $isMovie ? ($item['title'] ?? '') : ($item['name'] ?? ''),
                'poster_path' => $item['poster_path'] ?? '',
                'release_date' => $isMovie ? ($item['release_date'] ?? null) : ($item['first_air_date'] ?? null),
                'type' => $isMovie ? 'Film' : 'Dizi',
                'raw_type' => $isMovie ? 'movie' : 'tv',
            ];
        }, array_slice($items, 0, 8)));
    }
};
?>

<div
    class="relative w-full"
    x-data="{ open: false }"
    x-on:click.outside="open = false"
>
    <div class="relative flex items-center bg-black/70 backdrop-blur-md border border-white/10 rounded-full overflow-hidden transition-all duration-300 focus-within:border-fuchsia-500 focus-within:shadow-[0_0_20px_rgba(217,70,239,0.25)]">
        <div class="pl-4 text-neutral-500 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <input
            type="text"
            wire:model.live.debounce.500ms="search"
            x-on:focus="open = true"
            x-on:keydown.escape="open = false"
            class="w-full bg-transparent text-white text-sm py-2.5 px-3 focus:outline-none placeholder-neutral-500"
            placeholder="Başka film veya dizi ara..."
            autocomplete="off"
        >
        <div wire:loading wire:target="search" class="pr-4 shrink-0">
            <div class="w-4 h-4 border-2 border-neutral-600 border-t-fuchsia-500 rounded-full animate-spin"></div>
        </div>
    </div>

    @if(! empty($results))
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute top-full left-0 right-0 mt-2 bg-neutral-950/95 backdrop-blur-lg border border-neutral-800 rounded-2xl overflow-hidden shadow-2xl z-50 max-h-96 overflow-y-auto"
        >
            @foreach($results as $result)
                <button
                    wire:click="goToGallery('{{ $result['raw_type'] }}', {{ $result['id'] }})"
                    wire:key="gs-{{ $result['raw_type'] }}-{{ $result['id'] }}"
                    type="button"
                    class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-fuchsia-500/10 transition-colors text-left border-b border-white/5 last:border-b-0 cursor-pointer"
                >
                    <img
                        src="https://image.tmdb.org/t/p/w92{{ $result['poster_path'] }}"
                        class="w-8 h-12 object-cover rounded shrink-0 bg-neutral-800"
                        loading="lazy"
                        alt=""
                    >
                    <div class="min-w-0 flex-1">
                        <p class="text-white/90 text-sm font-medium truncate">{{ $result['title'] }}</p>
                        <p class="text-[11px] text-neutral-500">
                            {{ $result['type'] }}@if($result['release_date']) &middot; {{ substr($result['release_date'], 0, 4) }}@endif
                        </p>
                    </div>
                    <svg class="w-4 h-4 text-neutral-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            @endforeach
        </div>
    @endif
</div>
