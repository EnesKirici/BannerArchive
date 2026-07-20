<div
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative"
>
    <button
        @click="open = !open"
        type="button"
        aria-label="Araçlar"
        class="cursor-pointer p-2.5 rounded-full text-neutral-300 hover:text-white hover:bg-white/10 transition-colors"
        :class="open && 'bg-white/10 text-white'"
    >
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/>
            <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
            <circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/>
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 top-full mt-2 w-72 origin-top-right bg-neutral-900/95 backdrop-blur-xl border border-white/10 rounded-3xl shadow-2xl shadow-black/60 p-3 z-50"
    >
        <p class="px-3 pt-1.5 pb-2 text-[10px] uppercase tracking-[0.2em] text-neutral-500 font-bold">Araçlar</p>
        <div class="grid grid-cols-2 gap-2">
            <a href="{{ route('tools.image-converter') }}" class="group flex flex-col items-center gap-2.5 p-4 rounded-2xl hover:bg-white/5 transition-colors">
                <span class="w-12 h-12 rounded-full bg-fuchsia-600/15 border border-fuchsia-500/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                    <svg class="w-6 h-6 text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <span class="text-xs text-neutral-300 group-hover:text-white font-medium text-center leading-tight">Resim<br>Dönüştürücü</span>
            </a>
            <a href="{{ route('tools.video-downloader') }}" class="group flex flex-col items-center gap-2.5 p-4 rounded-2xl hover:bg-white/5 transition-colors">
                <span class="w-12 h-12 rounded-full bg-cyan-600/15 border border-cyan-500/20 flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                    <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </span>
                <span class="text-xs text-neutral-300 group-hover:text-white font-medium text-center leading-tight">Video<br>İndirici</span>
            </a>
        </div>
    </div>
</div>
