<footer class="relative z-10 border-t border-white/5 bg-neutral-950/80 backdrop-blur-sm mt-auto">
    <div class="max-w-[1920px] mx-auto px-6 md:px-12 py-10">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">

            {{-- Logo & Açıklama --}}
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 mb-2 group">
                    <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-8 h-8 rounded-lg elw-logo-hover">
                    <span class="text-lg font-bold bg-clip-text text-transparent bg-linear-to-r from-white to-neutral-400 tracking-tight">BannerArchive</span>
                </a>
                <p class="text-sm text-neutral-500 leading-relaxed max-w-xs">
                    Film ve dizi banner'larını, afişlerini ve logolarını yüksek çözünürlükte arayın ve indirin.
                </p>
            </div>

            {{-- Hızlı Linkler --}}
            <ul class="flex items-center gap-4">
                <li>
                    <a href="{{ route('home') }}" class="text-sm text-neutral-500 hover:text-fuchsia-400 transition-colors duration-300">Ana Sayfa</a>
                </li>
                <li class="text-neutral-700">|</li>
                <li>
                    <a href="{{ route('home') }}#" onclick="document.querySelector('[wire\\:model\\.live\\.debounce\\.500ms]')?.focus(); return false;" class="text-sm text-neutral-500 hover:text-fuchsia-400 transition-colors duration-300">Film Ara</a>
                </li>
                <li class="text-neutral-700">|</li>
                <li>
                    <a href="{{ route('tools.image-converter') }}" class="text-sm text-neutral-500 hover:text-fuchsia-400 transition-colors duration-300">Resim Dönüştürücü</a>
                </li>
            </ul>

        </div>

        {{-- Alt çizgi --}}
        <div class="border-t border-white/5 mt-8 pt-6 flex items-center justify-center">
            <p class="text-xs text-neutral-600 flex items-center gap-1.5">
                &copy; {{ date('Y') }} elw. Powered by
                <img src="{{ asset('images/tmdb_logo.svg') }}" alt="TMDB" class="h-3.5 inline-block opacity-50 hover:opacity-80 transition-opacity duration-500">
            </p>
        </div>
    </div>
</footer>
