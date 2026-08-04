@extends('layouts.app')

@section('title', __('messages.404_title'))

@section('content')
<div class="min-h-screen bg-neutral-950 text-white flex flex-col items-center justify-center relative overflow-hidden font-sans">

    {{-- Arka plan parıltıları --}}
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-fuchsia-600/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-1/3 w-[400px] h-[400px] bg-purple-600/10 rounded-full blur-3xl"></div>
    </div>

    {{-- İçerik --}}
    <div class="relative z-10 flex flex-col items-center text-center px-6">

        {{-- 404 Büyük Metin --}}
        <h1 class="text-[10rem] md:text-[14rem] font-black leading-none tracking-tighter bg-clip-text text-transparent bg-linear-to-r from-fuchsia-500 via-purple-500 to-cyan-400 select-none">
            404
        </h1>

        {{-- İkon --}}
        <div class="mb-6 -mt-4">
            <svg class="w-12 h-12 text-neutral-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h2 class="text-2xl md:text-3xl font-bold text-white/90 mb-3">{{ __('messages.404_heading') }}</h2>
        <p class="text-neutral-400 text-sm md:text-base max-w-sm mb-10">
            {{ __('messages.404_description') }}
        </p>

        {{-- Geri sayım --}}
        <div class="flex items-center gap-3 px-5 py-3 rounded-full bg-white/5 border border-white/10 text-neutral-400 text-sm mb-8">
            <svg class="w-4 h-4 text-fuchsia-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span><span id="countdown" class="text-white font-semibold">5</span> {{ __('messages.404_redirect') }}</span>
        </div>

        {{-- Ana sayfa butonu --}}
        <a href="{{ route('home') }}"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-fuchsia-600 hover:bg-fuchsia-500 text-white text-sm font-semibold transition-all duration-200 hover:scale-105 shadow-lg shadow-fuchsia-900/40">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            {{ __('messages.404_back') }}
        </a>

        {{-- Logo --}}
        <div class="mt-16 flex items-center gap-2 text-neutral-600 text-xs">
            <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-5 h-5 rounded opacity-40">
            <span>BannerArchive</span>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let seconds = 5;
    const el = document.getElementById('countdown');
    const timer = setInterval(() => {
        seconds--;
        el.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = '{{ route('home') }}';
        }
    }, 1000);
</script>
@endpush
@endsection
