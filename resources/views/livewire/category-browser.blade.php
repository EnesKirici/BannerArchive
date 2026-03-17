<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    public string $mediaType = 'movie';

    public string $category = 'popular';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public function mount(): void
    {
        $this->fetchItems();
    }

    public function setMediaType(string $type): void
    {
        $this->mediaType = $type;

        if ($type === 'tv' && in_array($this->category, ['now_playing', 'upcoming'])) {
            $this->category = $this->category === 'now_playing' ? 'airing_today' : 'on_the_air';
        } elseif ($type === 'movie' && in_array($this->category, ['airing_today', 'on_the_air'])) {
            $this->category = $this->category === 'airing_today' ? 'now_playing' : 'upcoming';
        }

        $this->fetchItems();
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
        $this->fetchItems();
    }

    /**
     * @return array<string, string>
     */
    public function getCategories(): array
    {
        if ($this->mediaType === 'movie') {
            return [
                'now_playing' => 'Vizyonda',
                'popular' => 'Popüler',
                'top_rated' => 'En İyi Puan',
                'upcoming' => 'Yakında',
            ];
        }

        return [
            'airing_today' => 'Bugün Yayında',
            'on_the_air' => 'Devam Eden',
            'popular' => 'Popüler',
            'top_rated' => 'En İyi Puan',
        ];
    }

    private function fetchItems(): void
    {
        $cacheKey = "tmdb_category_{$this->mediaType}_{$this->category}";

        $this->items = Cache::remember($cacheKey, now()->addHours(3), function (): array {
            $apiKey = config('services.tmdb.api_key');
            $baseUrl = config('services.tmdb.base_url');

            $response = Http::get("{$baseUrl}/{$this->mediaType}/{$this->category}", [
                'api_key' => $apiKey,
                'language' => 'tr-TR',
                'page' => 1,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $results = $response->json()['results'] ?? [];

            return collect($results)
                ->filter(fn (array $item): bool => ! empty($item['backdrop_path']))
                ->take(12)
                ->map(fn (array $item): array => $this->formatItem($item))
                ->values()
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function formatItem(array $item): array
    {
        $isMovie = $this->mediaType === 'movie';

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

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <div class="flex items-center gap-4">
            <h2 class="text-sm font-bold text-neutral-400 uppercase tracking-widest shrink-0">Keşfet</h2>
            <div class="h-px w-12 bg-white/5"></div>
        </div>

        {{-- Film / Dizi Toggle --}}
        <div class="flex items-center gap-1 bg-neutral-900/80 rounded-full p-1 border border-white/5">
            <button wire:click="setMediaType('movie')"
                    class="cursor-pointer px-3 py-1 rounded-full text-xs font-bold transition-all
                        {{ $mediaType === 'movie'
                            ? 'bg-fuchsia-600 text-white shadow-lg shadow-fuchsia-600/25'
                            : 'text-neutral-400 hover:text-white' }}">
                Film
            </button>
            <button wire:click="setMediaType('tv')"
                    class="cursor-pointer px-3 py-1 rounded-full text-xs font-bold transition-all
                        {{ $mediaType === 'tv'
                            ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/25'
                            : 'text-neutral-400 hover:text-white' }}">
                Dizi
            </button>
        </div>
    </div>

    {{-- Category Tabs --}}
    <div class="flex items-center gap-2 mb-6">
        @foreach ($this->getCategories() as $key => $label)
            <button wire:click="setCategory('{{ $key }}')"
                    wire:key="tab-{{ $key }}"
                    class="cursor-pointer px-4 py-1.5 rounded-full text-xs font-bold transition-all
                        {{ $category === $key
                            ? 'bg-white text-black shadow-[0_0_15px_rgba(255,255,255,0.3)]'
                            : 'bg-neutral-800/80 text-neutral-400 hover:text-white hover:bg-neutral-700 border border-white/10' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Results Grid with Loading Overlay --}}
    <div class="relative min-h-[300px]">
        {{-- Loading Overlay --}}
        <div wire:loading.flex wire:target="setCategory, setMediaType" class="absolute inset-0 z-10 items-center justify-center hidden">
            <div class="inline-block w-8 h-8 border-[3px] border-neutral-800 border-t-fuchsia-500 rounded-full animate-spin"></div>
        </div>

        <div wire:loading.class="opacity-30 pointer-events-none" wire:target="setCategory, setMediaType" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 transition-opacity duration-300">
        @foreach ($items as $item)
            <a href="{{ route('gallery', ['type' => $item['raw_type'], 'id' => $item['id']]) }}"
               wire:key="cat-{{ $item['id'] }}"
               class="group relative aspect-video rounded-xl overflow-hidden bg-neutral-900 border border-white/5 hover:border-fuchsia-500/50 transition-all duration-300 hover:shadow-[0_0_30px_rgba(217,70,239,0.15)]">
                <img src="https://image.tmdb.org/t/p/w780{{ $item['backdrop_path'] }}"
                     alt="{{ $item['title'] }}"
                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                     loading="lazy">
                <div class="absolute inset-0 bg-linear-to-t from-black/80 via-black/20 to-transparent"></div>
                <div class="absolute bottom-0 left-0 right-0 p-3">
                    <h3 class="text-white font-bold text-sm leading-tight truncate group-hover:text-fuchsia-400 transition-colors">{{ $item['title'] }}</h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-600/30 text-fuchsia-400 border border-fuchsia-600/20">{{ $item['type'] }}</span>
                        @if ($item['release_date'])
                            <span class="text-[10px] text-neutral-400">{{ \Carbon\Carbon::parse($item['release_date'])->format('Y') }}</span>
                        @endif
                        @if ($item['vote_average'])
                            <span class="text-[10px] text-yellow-500 flex items-center gap-0.5">
                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                {{ number_format($item['vote_average'], 1) }}
                            </span>
                        @endif
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if (empty($items))
        <div wire:loading.remove wire:target="setCategory, setMediaType" class="text-center py-12">
            <p class="text-neutral-500">Bu kategoride içerik bulunamadı.</p>
        </div>
    @endif
    </div>
</div>
