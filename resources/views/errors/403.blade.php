@extends('layouts.app')

@section('title', '403 — Erişim Engellendi')

@section('content')
<div class="min-h-screen bg-neutral-950 text-white flex flex-col items-center justify-center relative overflow-hidden font-sans">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-orange-600/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-1/3 w-[400px] h-[400px] bg-purple-600/10 rounded-full blur-3xl"></div>
    </div>
    <div class="relative z-10 flex flex-col items-center text-center px-6">
        <h1 class="text-[10rem] md:text-[14rem] font-black leading-none tracking-tighter bg-clip-text text-transparent bg-linear-to-r from-orange-500 via-amber-500 to-yellow-500 select-none">403</h1>
        <div class="mb-6 -mt-4">
            <svg class="w-12 h-12 text-neutral-600 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
        </div>
        <h2 class="text-2xl md:text-3xl font-bold text-white/90 mb-3">Erişim Engellendi</h2>
        <p class="text-neutral-400 text-sm md:text-base max-w-sm mb-10">Bu sayfaya erişim yetkiniz bulunmuyor.</p>
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-sm font-semibold transition-all duration-200 hover:scale-105 shadow-lg shadow-orange-900/40">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            {{ __('messages.404_back') }}
        </a>
        <div class="mt-16 flex items-center gap-2 text-neutral-600 text-xs">
            <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-5 h-5 rounded opacity-40">
            <span>BannerArchive</span>
        </div>
    </div>
</div>
@endsection
