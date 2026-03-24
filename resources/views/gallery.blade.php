@extends('layouts.app')

@section('title', $movie['title'] . ' - Galeri')

@section('content')
<div class="flex flex-col min-h-screen bg-neutral-950 text-white font-sans">

    <div class="flex-1">
    {{-- Hero Banner --}}
    @if($movie['backdrop_path'])
    <div class="relative h-72 md:h-[28rem] overflow-hidden">
        <img src="https://image.tmdb.org/t/p/original{{ $movie['backdrop_path'] }}"
             alt="{{ $movie['title'] }}"
             class="w-full h-full object-cover object-center">
        <div class="absolute inset-0 bg-linear-to-t from-neutral-950 via-neutral-950/50 to-transparent"></div>
        <div class="absolute inset-0 bg-linear-to-r from-neutral-950/60 via-neutral-950/20 to-transparent"></div>
    </div>
    @endif

    {{-- Header --}}
    <div class="relative -mt-44 md:-mt-56 z-10 max-w-[1920px] mx-auto px-6 md:px-12" id="galleryContent">
        <div class="flex items-end gap-6 md:gap-8 mb-8">
            @if($movie['poster_path'])
            <img src="https://image.tmdb.org/t/p/w342{{ $movie['poster_path'] }}"
                 alt="{{ $movie['title'] }}"
                 class="w-28 md:w-44 rounded-2xl shadow-2xl border-2 border-white/10 shrink-0 hidden sm:block">
            @endif
            <div class="flex-1 min-w-0 pb-2">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm text-neutral-400 hover:text-white transition-colors mb-3 group">
                    <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Aramaya Dön
                </a>
                <h1 class="text-3xl md:text-5xl font-black tracking-tight text-white mb-3">{{ $movie['title'] }}</h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 sm:gap-x-4 text-sm text-neutral-300">
                    <span class="px-3 py-1 rounded-full bg-fuchsia-600/20 text-fuchsia-400 text-sm font-bold border border-fuchsia-600/30">{{ $movie['type'] }}</span>
                    @if($movie['release_date'])
                        <span class="text-base font-medium">{{ \Carbon\Carbon::parse($movie['release_date'])->format('Y') }}</span>
                    @endif
                    @if($movie['vote_average'])
                        <span class="flex items-center gap-1.5 text-base">
                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <span class="font-semibold">{{ number_format($movie['vote_average'], 1) }}</span>
                        </span>
                    @endif
                    @php
                        $totalImages = count($images['backdrops'] ?? []) + count($images['posters'] ?? []) + count($images['logos'] ?? []);
                    @endphp
                    <span class="text-neutral-500">{{ $totalImages }} görsel</span>
                </div>
                @if($movie['overview'])
                    <div class="mt-3 max-w-2xl" id="overviewContainer">
                        <p class="text-sm text-neutral-400 line-clamp-2" id="overviewText">{{ $movie['overview'] }}</p>
                        @if(mb_strlen($movie['overview']) > 120)
                            <button
                                id="overviewToggle"
                                class="mt-1.5 text-xs font-medium text-fuchsia-400 hover:text-fuchsia-300 transition-colors flex items-center gap-1 cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5 transition-transform" id="overviewArrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                <span id="overviewLabel">Devamını Oku</span>
                            </button>
                        @endif
                    </div>
                @endif

                {{-- Watch Providers --}}
                @php
                    $flatrate = collect($watchProviders['flatrate'] ?? [])->unique('provider_id');
                    $rent = collect($watchProviders['rent'] ?? [])->unique('provider_id');
                    $buy = collect($watchProviders['buy'] ?? [])->unique('provider_id');
                    // Rent/buy'da olup flatrate'de olmayan platformları göster
                    $flatrateIds = $flatrate->pluck('provider_id')->all();
                    $rentFiltered = $rent->reject(fn($p) => in_array($p['provider_id'], $flatrateIds))->take(5);
                    $buyFiltered = $buy->reject(fn($p) => in_array($p['provider_id'], $flatrateIds) || $rent->pluck('provider_id')->contains($p['provider_id']))->take(3);
                @endphp
                @if($flatrate->isNotEmpty() || $rentFiltered->isNotEmpty() || $buyFiltered->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-3">
                        @if($flatrate->isNotEmpty())
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase tracking-widest text-emerald-500 font-bold shrink-0">Abone:</span>
                                @foreach($flatrate->take(6) as $provider)
                                    <img src="https://image.tmdb.org/t/p/w45{{ $provider['logo_path'] }}"
                                         alt="{{ $provider['provider_name'] }}"
                                         title="{{ $provider['provider_name'] }} (Abonelik)"
                                         class="w-7 h-7 rounded-md object-cover border border-emerald-500/30 hover:border-emerald-400 transition-colors">
                                @endforeach
                            </div>
                        @endif
                        @if($rentFiltered->isNotEmpty())
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase tracking-widest text-blue-400 font-bold shrink-0">Kirala:</span>
                                @foreach($rentFiltered as $provider)
                                    <img src="https://image.tmdb.org/t/p/w45{{ $provider['logo_path'] }}"
                                         alt="{{ $provider['provider_name'] }}"
                                         title="{{ $provider['provider_name'] }} (Kiralama)"
                                         class="w-7 h-7 rounded-md object-cover border border-blue-500/30 hover:border-blue-400 transition-colors">
                                @endforeach
                            </div>
                        @endif
                        @if($buyFiltered->isNotEmpty())
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] uppercase tracking-widest text-amber-400 font-bold shrink-0">Satın Al:</span>
                                @foreach($buyFiltered as $provider)
                                    <img src="https://image.tmdb.org/t/p/w45{{ $provider['logo_path'] }}"
                                         alt="{{ $provider['provider_name'] }}"
                                         title="{{ $provider['provider_name'] }} (Satın Alma)"
                                         class="w-7 h-7 rounded-md object-cover border border-amber-500/30 hover:border-amber-400 transition-colors">
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        @php
            $tabData = [
                'backdrops' => ['Banner', count($images['backdrops'] ?? [])],
                'posters' => ['Poster', count($images['posters'] ?? [])],
                'logos' => ['Logo', count($images['logos'] ?? [])],
                'cast' => ['Oyuncular', count($credits['cast'] ?? [])],
                'videos' => ['Fragmanlar', count($videos ?? [])],
            ];
            $firstTab = collect($tabData)->filter(fn($v) => $v[1] > 0)->keys()->first() ?? 'backdrops';
        @endphp
        <div id="galleryTabs" class="flex items-center gap-2 mb-5 overflow-x-auto scrollbar-hide -mx-6 px-6 md:mx-0 md:px-0 md:overflow-visible">
            @foreach($tabData as $key => [$label, $count])
                @if($count > 0)
                    <button data-tab="{{ $key }}"
                            class="gallery-tab px-5 py-2 rounded-full text-sm font-bold transition-all {{ $key === $firstTab ? 'bg-fuchsia-600 text-white' : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700' }}">
                        {{ $label }} <span class="ml-1 opacity-60">{{ $count }}</span>
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Controls Bar --}}
        <div id="controlsBar" class="flex flex-wrap items-center gap-x-4 gap-y-3 mb-6 p-3 sm:p-4 bg-neutral-900/80 backdrop-blur-sm rounded-xl border border-white/5">
            {{-- Resolution --}}
            <div id="sizeButtons" class="flex items-center gap-2 overflow-x-auto scrollbar-hide max-w-full">
                <span class="text-[10px] uppercase tracking-widest text-neutral-500 font-bold">Çözünürlük:</span>
                {{-- JS tarafindan doldurulacak --}}
            </div>

            {{-- Format --}}
            <div class="flex items-center gap-2">
                <span class="text-[10px] uppercase tracking-widest text-neutral-500 font-bold">Format:</span>
                @foreach(['webp', 'png', 'jpg'] as $fmt)
                    <button data-format="{{ $fmt }}" class="format-btn px-2.5 py-1 rounded text-[11px] font-mono font-bold transition-all uppercase {{ $fmt === 'webp' ? 'bg-fuchsia-600 text-white' : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700 border border-white/5' }}">
                        {{ strtoupper($fmt) }}
                    </button>
                @endforeach
            </div>

            {{-- Bulk Actions --}}
            <div class="flex items-center gap-3 ml-auto w-full sm:w-auto mt-2 sm:mt-0">
                <button id="selectAllBtn" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all bg-neutral-800 text-neutral-400 hover:bg-neutral-700 border border-white/5">
                    Tümünü Seç
                </button>
                <button id="downloadSelectedBtn" class="hidden px-4 py-1.5 rounded-lg text-xs font-bold transition-all bg-fuchsia-600 text-white hover:bg-fuchsia-500 items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span id="downloadSelectedCount">0 Görsel İndir</span>
                </button>
            </div>
        </div>

        {{-- Particles: Overlay mode = tum sayfa, Background mode = sadece grid arkasi --}}
        @if(($particlesLayer ?? 'background') === 'overlay')
            <div id="galleryParticles" class="fixed inset-0 z-0 pointer-events-none"></div>
        @endif

        {{-- Image Grids (her tab icin ayri, JS ile goster/gizle) --}}
        <div class="relative overflow-hidden">
            @if(($particlesLayer ?? 'background') === 'background')
                <div id="galleryParticles" class="absolute inset-0 z-0 pointer-events-none overflow-hidden"></div>
            @endif

            <div class="relative z-10">
                @foreach(['backdrops', 'posters', 'logos'] as $tabKey)
                    @if(count($images[$tabKey] ?? []) > 0)
                        <div id="grid-{{ $tabKey }}" class="gallery-grid pb-12 {{ $tabKey !== $firstTab ? 'hidden' : '' }}">
                            <div class="grid gap-4 {{ $tabKey === 'posters' ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4' }}">
                                @foreach($images[$tabKey] as $index => $img)
                                    @if($index >= 20) @break @endif
                                    <div class="gallery-item group relative bg-neutral-900 rounded-xl overflow-hidden border-2 border-transparent hover:border-white/20 transition-all cursor-pointer"
                                         data-tab="{{ $tabKey }}"
                                         data-index="{{ $index }}"
                                         data-file-path="{{ $img['file_path'] }}"
                                         data-width="{{ $img['width'] }}"
                                         data-height="{{ $img['height'] }}">

                                            {{-- Checkbox --}}
                                            <div class="absolute top-2 left-2 z-10">
                                                <label class="gallery-checkbox flex items-center justify-center w-6 h-6 rounded-md transition-all cursor-pointer bg-black/50 backdrop-blur-sm border border-white/20 hover:border-fuchsia-500">
                                                    <input type="checkbox" class="hidden gallery-check-input">
                                                    <svg class="w-4 h-4 text-white hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </label>
                                            </div>

                                            {{-- Image --}}
                                            <div class="{{ $tabKey === 'posters' ? 'aspect-2/3' : 'aspect-video' }}">
                                                <img src="https://image.tmdb.org/t/p/{{ $tabKey === 'posters' ? 'w342' : 'w780' }}{{ $img['file_path'] }}"
                                                     alt="Görsel {{ $index + 1 }}"
                                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                     loading="lazy">
                                            </div>

                                            {{-- Info Bar --}}
                                            <div class="flex items-center justify-between px-3 py-2 bg-neutral-900">
                                                <span class="text-[10px] font-mono text-neutral-500">{{ $img['width'] }}x{{ $img['height'] }}</span>
                                                <button class="download-single-btn flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold bg-neutral-800 text-neutral-400 hover:bg-fuchsia-600 hover:text-white transition-all">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                    <span class="format-label">WEBP</span>
                                                </button>
                                            </div>
                                    </div>
                                @endforeach
                            </div>
                            @if(count($images[$tabKey]) > 20)
                                <div class="lazy-load-sentinel h-px" data-tab="{{ $tabKey }}" data-loaded="20"></div>
                            @endif
                        </div>
                    @endif
                @endforeach

                {{-- Cast Grid --}}
                @if(count($credits['cast'] ?? []) > 0)
                    <div id="grid-cast" class="gallery-grid pb-12 hidden">
                        <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
                            @foreach($credits['cast'] as $actor)
                                <div class="cast-card group relative bg-neutral-900 rounded-xl overflow-hidden border border-white/5 hover:border-fuchsia-500/50 transition-all cursor-pointer"
                                     data-person-id="{{ $actor['id'] }}">
                                    <div class="aspect-2/3">
                                        @if($actor['profile_path'] ?? null)
                                            <img src="https://image.tmdb.org/t/p/w342{{ $actor['profile_path'] }}"
                                                 alt="{{ $actor['name'] }}"
                                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                 loading="lazy">
                                        @else
                                            <div class="w-full h-full bg-neutral-800 flex items-center justify-center">
                                                <svg class="w-12 h-12 text-neutral-700" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="px-3 py-2.5 bg-neutral-900">
                                        <p class="text-xs font-bold text-white truncate">{{ $actor['name'] }}</p>
                                        <p class="text-[10px] text-neutral-500 truncate mt-0.5">{{ $actor['character'] ?? '' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Videos Grid --}}
                @if(count($videos ?? []) > 0)
                    <div id="grid-videos" class="gallery-grid pb-12 hidden">
                        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                            @foreach($videos as $video)
                                <div class="video-card group relative bg-neutral-900 rounded-xl overflow-hidden border border-white/5 hover:border-fuchsia-500/50 transition-all cursor-pointer"
                                     data-video-key="{{ $video['key'] }}"
                                     data-video-name="{{ $video['name'] }}">
                                    <div class="aspect-video relative">
                                        <img src="https://img.youtube.com/vi/{{ $video['key'] }}/mqdefault.jpg"
                                             alt="{{ $video['name'] }}"
                                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                             loading="lazy">
                                        {{-- Play icon overlay --}}
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/10 transition-colors">
                                            <div class="w-14 h-14 rounded-full bg-fuchsia-600/90 flex items-center justify-center shadow-xl group-hover:scale-110 transition-transform">
                                                <svg class="w-6 h-6 text-white ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </div>
                                        </div>
                                        {{-- Type badge --}}
                                        <div class="absolute top-2 left-2">
                                            <span class="text-[9px] font-bold px-2 py-1 rounded-md bg-black/70 text-fuchsia-400 backdrop-blur-sm border border-fuchsia-600/20">
                                                {{ $video['type'] === 'Trailer' ? 'Fragman' : ($video['type'] === 'Teaser' ? 'Teaser' : ($video['type'] === 'Clip' ? 'Klip' : 'Kamera Arkası')) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="px-3 py-2.5 bg-neutral-900">
                                        <p class="text-xs font-bold text-white truncate group-hover:text-fuchsia-400 transition-colors">{{ $video['name'] }}</p>
                                        <p class="text-[10px] text-neutral-500 mt-0.5">{{ $video['iso_639_1'] ?? '' }} &middot; {{ $video['type'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
    </div>

    @include('partials.footer')

    {{-- Actor Modal --}}
    <div id="actorModal" class="fixed inset-0 z-50 hidden">
        <div id="actorModalBackdrop" class="absolute inset-0 bg-black/90 backdrop-blur-md"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 md:p-8">
            <div class="relative w-full max-w-4xl max-h-[85vh] bg-neutral-900 rounded-2xl overflow-hidden border border-white/10 shadow-2xl flex flex-col">
                {{-- Close --}}
                <button id="actorModalClose" class="absolute top-4 right-4 z-10 p-2 bg-neutral-800/80 rounded-full hover:bg-fuchsia-600 text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                {{-- Loading --}}
                <div id="actorModalLoading" class="flex items-center justify-center py-20">
                    <div class="w-8 h-8 border-2 border-neutral-700 border-t-fuchsia-500 rounded-full animate-spin"></div>
                </div>

                {{-- Content --}}
                <div id="actorModalContent" class="hidden flex-1 overflow-y-auto"></div>
            </div>
        </div>
    </div>

    {{-- Video Player Modal --}}
    <div id="videoModal" class="fixed inset-0 z-50 hidden">
        <div id="videoModalBackdrop" class="absolute inset-0 bg-black/95 backdrop-blur-md"></div>
        <div class="absolute inset-0 flex flex-col items-center justify-center p-4 md:p-8">
            <div class="relative w-full max-w-5xl">
                {{-- Close --}}
                <button id="videoModalClose" class="absolute -top-10 right-0 z-10 p-2 text-neutral-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                {{-- Video Title --}}
                <p id="videoModalTitle" class="absolute -top-10 left-0 text-sm font-bold text-white truncate max-w-[80%]"></p>
                {{-- YouTube Embed --}}
                <div class="aspect-video rounded-2xl overflow-hidden border border-white/10 shadow-2xl bg-black">
                    <iframe id="videoModalIframe" class="w-full h-full" src="" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>

    {{-- Lightbox (vanilla JS ile yonetilir) --}}
    <div id="lightbox" class="fixed inset-0 z-50 hidden bg-black/95 backdrop-blur-md">
        {{-- Close --}}
        <button id="lightboxClose" class="absolute top-4 right-4 z-10 p-2 bg-neutral-800/80 rounded-full hover:bg-fuchsia-600 text-white transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        {{-- Prev --}}
        <button id="lightboxPrev" class="absolute left-4 top-1/2 -translate-y-1/2 z-10 p-3 bg-neutral-800/80 rounded-full hover:bg-fuchsia-600 text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>

        {{-- Next --}}
        <button id="lightboxNext" class="absolute right-4 top-1/2 -translate-y-1/2 z-10 p-3 bg-neutral-800/80 rounded-full hover:bg-fuchsia-600 text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>

        {{-- Preview Content --}}
        <div class="absolute inset-0 flex flex-col items-center justify-center p-4" id="lightboxBackdrop">
            <img id="lightboxImage" src="" alt="Preview" class="max-w-[90vw] max-h-[75vh] object-contain rounded-lg">

            <div class="mt-4 flex items-center gap-2 sm:gap-4 bg-neutral-900/90 backdrop-blur-sm px-3 py-2 sm:px-6 sm:py-3 flex-wrap justify-center rounded-2xl sm:rounded-full max-w-[95vw] border border-white/10">
                <span id="lightboxDims" class="text-xs font-mono text-fuchsia-400">...</span>
                <span id="lightboxCounter" class="text-xs text-neutral-500">1 / 22</span>
                <button id="lightboxDownload" class="flex items-center gap-2 px-4 py-1.5 bg-white text-black font-bold rounded-full text-xs hover:bg-fuchsia-500 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span id="lightboxDownloadLabel">WEBP İndir</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Download Progress Toast --}}
    <div id="downloadProgress" class="fixed bottom-6 right-6 z-50 hidden bg-neutral-900 border border-white/10 rounded-xl p-4 shadow-2xl min-w-[280px]">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-5 h-5 border-2 border-neutral-700 border-t-fuchsia-500 rounded-full animate-spin"></div>
            <span id="downloadProgressText" class="text-sm font-medium text-white">İndiriliyor... 0/0</span>
        </div>
        <div class="w-full bg-neutral-800 rounded-full h-1.5">
            <div id="downloadProgressBar" class="bg-fuchsia-600 h-1.5 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.GALLERY_IMAGES = @json($images);
    window.GALLERY_MOVIE = @json($movie);
    window.GALLERY_CREDITS = @json($credits);
</script>
@endpush
@push('scripts')
    @vite('resources/js/gallery.js')
@endpush
@endsection
