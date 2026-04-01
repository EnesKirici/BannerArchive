@extends('layouts.app')

@section('title', 'elw - BannerArchive')

@section('content')
@php $sidebarCollapsed = ($_COOKIE['sidebar_collapsed'] ?? '') === 'true'; @endphp
<div class="min-h-screen bg-neutral-950 text-white flex flex-col md:flex-row font-sans overflow-hidden" data-sidebar-collapsed="{{ $sidebarCollapsed ? 'true' : 'false' }}">

    <!-- Mobile Header -->
    <div class="md:hidden flex items-center justify-between p-4 border-b border-white/5 bg-neutral-900/95 backdrop-blur-md sticky top-0 z-40">
        <div class="flex items-center gap-2">
            <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-7 h-7 rounded elw-logo-hover object-cover aspect-square">
            <span class="font-bold tracking-tight">BannerArchive</span>
        </div>
        <button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')" class="cursor-pointer text-white p-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
    </div>

    <!-- Sidebar Toggle Button (Desktop) -->
    <button id="sidebarToggle" aria-label="Kenar çubuğunu aç/kapat" class="cursor-pointer hidden md:flex fixed left-80 top-1/2 -translate-y-1/2 z-50 w-6 h-14 items-center justify-center bg-neutral-800/90 border border-white/10 rounded-r-lg hover:bg-fuchsia-600 transition-all duration-300 text-neutral-400 hover:text-white backdrop-blur-sm">
        <svg id="toggleArrow" class="w-4 h-4 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
    </button>

    <!-- Left Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 bg-neutral-900 border-r border-white/5 transform -translate-x-full md:relative md:translate-x-0 transition-all duration-300 z-50 flex flex-col h-[calc(100vh-65px)] md:h-screen">

        <!-- Sidebar Header -->
        <div class="p-6 pb-4 hidden md:block shrink-0 bg-neutral-900 z-20">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-9 h-9 rounded-lg elw-logo-animated elw-logo-hover object-cover aspect-square">
                <h2 class="text-xl font-bold bg-clip-text text-transparent bg-linear-to-r from-white to-neutral-400 tracking-tight">BannerArchive</h2>
            </div>
        </div>

        <!-- Scrollable Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- POPULAR MOVIES -->
            <div class="flex-1 overflow-hidden border-b border-white/5">
                <div class="px-4 bg-neutral-900 z-20">
                    <h3 class="py-3 text-[10px] uppercase tracking-[0.2em] text-fuchsia-500 font-bold flex items-center gap-2 border-b border-white/5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path></svg>
                        Trend Filmler
                    </h3>
                </div>
                <div class="sidebar-slider overflow-hidden h-full px-4 scrollbar-hide">
                    <div class="slider-track space-y-2 py-2">
                        @foreach($popularMovies as $item)
                            <button
                                type="button"
                                class="sidebar-item group flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-white/5 transition-all cursor-pointer"
                                data-sidebar-item
                                data-tmdb-id="{{ $item['id'] }}"
                                data-tmdb-title="{{ $item['title'] }}"
                                data-tmdb-overview="{{ $item['overview'] }}"
                                data-tmdb-poster-path="{{ $item['poster_path'] }}"
                                data-tmdb-backdrop-path="{{ $item['backdrop_path'] }}"
                                data-tmdb-vote-average="{{ $item['vote_average'] }}"
                                data-tmdb-release-date="{{ $item['release_date'] }}"
                                data-tmdb-type="{{ $item['type'] }}"
                                data-tmdb-raw-type="{{ $item['raw_type'] }}"
                            >
                                <img src="https://image.tmdb.org/t/p/w92{{ $item['poster_path'] }}" class="w-10 h-14 object-cover rounded shadow-md group-hover:scale-105 transition-transform bg-neutral-800" alt="{{ $item['title'] }}">
                                <div class="min-w-0">
                                    <h4 class="text-sm text-neutral-300 group-hover:text-white truncate">{{ $item['title'] }}</h4>
                                    <span class="text-xs text-neutral-500">{{ $item['release_date'] ? \Carbon\Carbon::parse($item['release_date'])->format('Y') : '' }}</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- POPULAR SHOWS -->
            <div class="flex-1 overflow-hidden">
                <div class="px-4 bg-neutral-900 z-20">
                    <h3 class="py-3 text-[10px] uppercase tracking-[0.2em] text-purple-500 font-bold flex items-center gap-2 border-b border-white/5">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                        Trend Diziler
                    </h3>
                </div>
                <div class="sidebar-slider overflow-hidden h-full px-4 scrollbar-hide">
                    <div class="slider-track space-y-2 py-2">
                        @foreach($popularShows as $item)
                            <button
                                type="button"
                                class="sidebar-item group flex w-full items-center gap-3 rounded-lg p-2 text-left hover:bg-white/5 transition-all cursor-pointer"
                                data-sidebar-item
                                data-tmdb-id="{{ $item['id'] }}"
                                data-tmdb-title="{{ $item['title'] }}"
                                data-tmdb-overview="{{ $item['overview'] }}"
                                data-tmdb-poster-path="{{ $item['poster_path'] }}"
                                data-tmdb-backdrop-path="{{ $item['backdrop_path'] }}"
                                data-tmdb-vote-average="{{ $item['vote_average'] }}"
                                data-tmdb-release-date="{{ $item['release_date'] }}"
                                data-tmdb-type="{{ $item['type'] }}"
                                data-tmdb-raw-type="{{ $item['raw_type'] }}"
                            >
                                <img src="https://image.tmdb.org/t/p/w92{{ $item['poster_path'] }}" class="w-10 h-14 object-cover rounded shadow-md group-hover:scale-105 transition-transform bg-neutral-800" alt="{{ $item['title'] }}">
                                <div class="min-w-0">
                                    <h4 class="text-sm text-neutral-300 group-hover:text-white truncate">{{ $item['title'] }}</h4>
                                    <span class="text-xs text-neutral-500">{{ number_format($item['vote_average'], 1) }} Puan</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-[calc(100vh-65px)] md:h-screen overflow-y-auto relative scroll-smooth bg-neutral-950">

        <!-- Particles Background -->
        <div id="tsparticles" class="absolute! inset-0 z-0 pointer-events-none"></div>

        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-linear-to-b from-transparent via-neutral-950/50 to-neutral-950 pointer-events-none z-1"></div>

        <!-- Hero Section -->
        <div class="relative w-full px-4 md:px-12 flex flex-col items-center pt-[12vh] text-center z-10">
            <h1 class="relative text-5xl md:text-7xl font-black tracking-tighter mb-4">
                <span class="block text-white/90">Search Movies</span>
                <span class="block bg-clip-text text-transparent bg-linear-to-r from-fuchsia-500 via-purple-500 to-cyan-400">& TV Shows</span>
            </h1>
            <p class="text-neutral-400 text-sm md:text-base mb-10 max-w-md">Film ve dizi bannerlarını, afişlerini ve logolarını yüksek çözünürlükte indirin</p>

            <livewire:movie-search />

            {{-- Özellik Rozetleri --}}
            <div class="flex flex-wrap justify-center gap-3 mt-14">
                @foreach([
                    ['HD Bannerlar', 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ['Toplu İndirme', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
                    ['Format Dönüştürme', 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
                    ['Afişler & Logolar', 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                ] as [$featureLabel, $featureIcon])
                    <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-neutral-400 text-xs font-medium">
                        <svg class="w-3.5 h-3.5 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $featureIcon }}"/></svg>
                        {{ $featureLabel }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Keşfet - Kategori Tarayıcı --}}
        <div class="relative z-10 w-full max-w-[1920px] mx-auto px-4 md:px-12 pb-16 mt-8">
            <livewire:category-browser />
        </div>

        @include('partials.footer')
    </main>
</div>

{{-- Gallery view mode ayari JS'e aktarilir --}}
<script>
    window.galleryViewMode = @json($galleryViewMode);
</script>

<!-- Image Modal (JS-driven — Canvas API gerektigi icin Livewire'a cevirilmedi) -->
<div id="imageModal" class="fixed inset-0 z-60 hidden">
    <div class="modal-backdrop absolute inset-0 bg-black/95 backdrop-blur-md transition-opacity duration-300 opacity-0"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4 md:p-8 pointer-events-none">
        <div id="modalContainer" class="w-full max-w-6xl bg-neutral-900 rounded-2xl overflow-hidden shadow-2xl border border-white/10 flex flex-col max-h-[90vh] pointer-events-auto transform transition-all duration-300 scale-95 opacity-0">
            <div id="modalContent" class="flex-1 flex flex-col overflow-hidden min-h-0"></div>
        </div>
    </div>
</div>

{{-- ═══ LIVEWIRE: QuoteGenerator Component ═══
     AI soz uretme modal'i — tamamen Livewire ile calisiyor
     Eski JS quotes modal'i yerine sunucu-driven modal
     Image modal'daki "AI Sozleri" butonu bu component'i tetikler --}}
<livewire:quote-generator />
@endsection
