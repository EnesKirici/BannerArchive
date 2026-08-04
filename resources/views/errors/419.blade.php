@extends('layouts.app')

@section('title', '419 — Oturum Süresi Doldu')

@section('content')
<div class="min-h-screen bg-neutral-950 text-white flex flex-col items-center justify-center relative overflow-hidden font-sans">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-cyan-600/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-1/3 w-[400px] h-[400px] bg-purple-600/10 rounded-full blur-3xl"></div>
    </div>
    <div class="relative z-10 flex flex-col items-center text-center px-6">
        <h1 class="text-[10rem] md:text-[14rem] font-black leading-none tracking-tighter bg-clip-text text-transparent bg-linear-to-r from-cyan-500 via-blue-500 to-indigo-500 select-none">419</h1>
        <div class="mb-6 -mt-4">
            <svg class="w-12 h-12 text-neutral-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </div>
        <h2 class="text-2xl md:text-3xl font-bold text-white/90 mb-3">Oturum Süresi Doldu</h2>
        <p class="text-neutral-400 text-sm md:text-base max-w-sm mb-10">Güvenlik nedeniyle oturumunuz sona erdi. Lütfen sayfayı yenileyip tekrar deneyin.</p>
        <button onclick="location.reload()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-semibold transition-all duration-200 hover:scale-105 shadow-lg shadow-cyan-900/40 border-none cursor-pointer font-[inherit]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
            Sayfayı Yenile
        </button>
        <div class="mt-16 flex items-center gap-2 text-neutral-600 text-xs">
            <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-5 h-5 rounded opacity-40">
            <span>BannerArchive</span>
        </div>
    </div>
</div>
@endsection
