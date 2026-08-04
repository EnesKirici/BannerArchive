@extends('layouts.app')

@section('title', '429 — Çok Fazla İstek')

@section('content')
<div class="min-h-screen bg-neutral-950 text-white flex flex-col items-center justify-center relative overflow-hidden font-sans">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-violet-600/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-1/3 w-[400px] h-[400px] bg-fuchsia-600/10 rounded-full blur-3xl"></div>
    </div>
    <div class="relative z-10 flex flex-col items-center text-center px-6">
        <h1 class="text-[10rem] md:text-[14rem] font-black leading-none tracking-tighter bg-clip-text text-transparent bg-linear-to-r from-violet-500 via-purple-500 to-fuchsia-500 select-none">429</h1>
        <div class="mb-6 -mt-4">
            <svg class="w-12 h-12 text-neutral-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
            </svg>
        </div>
        <h2 class="text-2xl md:text-3xl font-bold text-white/90 mb-3">Çok Fazla İstek</h2>
        <p class="text-neutral-400 text-sm md:text-base max-w-sm mb-10">Kısa sürede çok fazla istek gönderildi. Lütfen biraz bekleyip tekrar deneyin.</p>
        <div class="flex gap-3 flex-wrap justify-center">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-sm font-semibold transition-all duration-200 hover:scale-105 shadow-lg shadow-violet-900/40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                {{ __('messages.404_back') }}
            </a>
            <button onclick="location.reload()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-neutral-400 hover:text-white text-sm font-semibold transition-all cursor-pointer font-[inherit]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                Yenile
            </button>
        </div>
        <div class="mt-16 flex items-center gap-2 text-neutral-600 text-xs">
            <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-5 h-5 rounded opacity-40">
            <span>BannerArchive</span>
        </div>
    </div>
</div>
@endsection
