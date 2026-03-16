<footer class="relative z-10 border-t border-white/5 bg-neutral-950/80 backdrop-blur-sm mt-auto">
    <div class="max-w-[1920px] mx-auto px-6 md:px-12 py-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            {{-- Logo & Açıklama --}}
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 mb-3 group">
                    <div class="w-8 h-8 flex items-center justify-center rounded-lg bg-linear-to-br from-fuchsia-600 to-purple-700 shadow-[0_0_15px_rgba(217,70,239,0.3)] group-hover:shadow-[0_0_20px_rgba(217,70,239,0.5)] transition-shadow duration-500">
                        <span class="font-bold text-white text-sm">B</span>
                    </div>
                    <span class="text-lg font-bold bg-clip-text text-transparent bg-linear-to-r from-white to-neutral-400 tracking-tight">BannerArchive</span>
                </a>
                <p class="text-sm text-neutral-500 leading-relaxed max-w-xs">
                    Film ve dizi banner'larını, afişlerini ve logolarını yüksek çözünürlükte arayın ve indirin.
                </p>
            </div>

            {{-- Hızlı Linkler --}}
            <div>
                <h4 class="text-xs uppercase tracking-[0.2em] text-neutral-400 font-bold mb-4">Hızlı Erişim</h4>
                <ul class="space-y-2.5">
                    <li>
                        <a href="{{ route('home') }}" class="text-sm text-neutral-500 hover:text-fuchsia-400 transition-colors duration-300">Ana Sayfa</a>
                    </li>
                    <li>
                        <a href="{{ route('home') }}#" onclick="document.querySelector('[wire\\:model\\.live\\.debounce\\.500ms]')?.focus(); return false;" class="text-sm text-neutral-500 hover:text-fuchsia-400 transition-colors duration-300">Film Ara</a>
                    </li>
                </ul>
            </div>

            {{-- TMDB Attribution --}}
            <div>
                <div class="mb-3">
                    <img src="{{ asset('images/tmdb_logo.svg') }}" alt="TMDB" class="h-5 opacity-60 hover:opacity-100 transition-opacity duration-500">
                </div>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Bu site TMDB API kullanmaktadır ancak TMDB tarafından onaylanmamış veya sertifikalandırılmamıştır.
                </p>
            </div>
        </div>

        {{-- Alt çizgi --}}
        <div class="border-t border-white/5 mt-8 pt-6 flex items-center justify-center">
            <p class="text-xs text-neutral-600">&copy; {{ date('Y') }} BannerArchive. Tüm hakları saklıdır.</p>
        </div>
    </div>
</footer>
