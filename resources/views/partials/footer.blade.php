<footer class="relative z-10 border-t border-white/5 bg-neutral-950/80 backdrop-blur-sm mt-auto">
    <div class="max-w-[1920px] mx-auto px-6 md:px-12 py-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            {{-- Logo & Aciklama --}}
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 mb-3 group">
                    <div class="w-8 h-8 flex items-center justify-center rounded-lg bg-linear-to-br from-fuchsia-600 to-purple-700 shadow-[0_0_15px_rgba(217,70,239,0.3)] group-hover:shadow-[0_0_20px_rgba(217,70,239,0.5)] transition-shadow">
                        <span class="font-bold text-white text-sm">B</span>
                    </div>
                    <span class="text-lg font-bold bg-clip-text text-transparent bg-linear-to-r from-white to-neutral-400 tracking-tight">BannerArchive</span>
                </a>
                <p class="text-sm text-neutral-500 leading-relaxed max-w-xs">
                    Film ve dizi banner'larini, afislerini ve logolarini yuksek cozunurlukte arayin ve indirin.
                </p>
            </div>

            {{-- Hizli Linkler --}}
            <div>
                <h4 class="text-xs uppercase tracking-[0.2em] text-neutral-400 font-bold mb-4">Hizli Erisim</h4>
                <ul class="space-y-2.5">
                    <li>
                        <a href="{{ route('home') }}" class="text-sm text-neutral-500 hover:text-fuchsia-400 transition-colors">Ana Sayfa</a>
                    </li>
                    <li>
                        <a href="{{ route('home') }}#" onclick="document.querySelector('[wire\\:model\\.live\\.debounce\\.500ms]')?.focus(); return false;" class="text-sm text-neutral-500 hover:text-fuchsia-400 transition-colors">Film Ara</a>
                    </li>
                </ul>
            </div>

            {{-- TMDB Attribution --}}
            <div>
                <h4 class="text-xs uppercase tracking-[0.2em] text-neutral-400 font-bold mb-4">Veri Kaynagi</h4>
                <div class="flex items-center gap-3 mb-3">
                    <svg class="w-8 h-8 text-[#01b4e4] shrink-0" viewBox="0 0 273.42 35.52" fill="currentColor">
                        <path d="M191.85 35.37h5.44V0h-5.44v35.37zm-9.1 0h5.44V8.3h-5.44v27.07zm0-31.31h5.44V0h-5.44v4.06zm-20.47 6.2v.52h-2.97v4.06h2.97V35.37h5.44V14.84h4.38V10.78h-4.38v-.7c0-2.93 1.5-4.06 4.38-4.06V1.27c-6.03 0-9.82 2.54-9.82 9.0zm-15.3-4.06h-5.44v4.06h5.44V4.2zm-5.44 6.58v24.6h5.44V10.78h-5.44zm-20.19-4.58V35.37h5.44V14.84h4.68c3.7 0 5.35 2.24 5.35 5.86v14.67h5.44V19.72c0-6.22-3.3-9.5-9.91-9.5h-10.99zm-16.2-6.2v35.37h5.44V14.84h5.06c3.7 0 5.35 2.24 5.35 5.86v14.67h5.44V19.72c0-6.22-3.3-9.5-9.91-9.5h-.94V0h-5.44v10.22h-5zm-26.83 6.2h15.68v4.06h-15.68v-4.06zm0 8.12h10.24c3.7 0 5.35 2.24 5.35 5.86v.4c0 3.62-1.65 5.86-5.35 5.86h-10.24v4.06h11.12c6.62 0 9.91-3.28 9.91-9.5v-1.26c0-6.22-3.3-9.5-9.91-9.5h-11.12v4.06h-.01zM50.3 0h-5.44v35.37h15.68v-4.06H50.3V0zm-21.18 6.2V35.37h5.44V14.84h4.68c3.7 0 5.35 2.24 5.35 5.86v14.67h5.44V19.72c0-6.22-3.3-9.5-9.91-9.5H29.12zm-17 24.34c-3.12 0-5.44-2.61-5.44-6.35v-8.68c0-3.74 2.32-6.35 5.44-6.35s5.44 2.61 5.44 6.35v8.68c0 3.74-2.32 6.35-5.44 6.35zm0 5.1c6.32 0 10.88-4.89 10.88-11.45V15.5C23 8.94 18.44 4.06 12.12 4.06S1.24 8.94 1.24 15.5v8.68c0 6.57 4.56 11.45 10.88 11.45zM227.44 6.2h15.68v4.06h-15.68V6.2zm0 8.12h10.24c3.7 0 5.35 2.24 5.35 5.86v.4c0 3.62-1.65 5.86-5.35 5.86h-10.24v4.06h11.12c6.62 0 9.91-3.28 9.91-9.5v-1.26c0-6.22-3.3-9.5-9.91-9.5h-11.12v4.06h-.01zm35.86 16.03c-3.12 0-5.44-2.61-5.44-6.35v-8.68c0-3.74 2.32-6.35 5.44-6.35s5.44 2.61 5.44 6.35v8.68c0 3.74-2.32 6.35-5.44 6.35zm0 5.1c6.32 0 10.88-4.89 10.88-11.45V15.5c0-6.57-4.56-11.45-10.88-11.45S252.42 8.94 252.42 15.5v8.68c0 6.57 4.56 11.45 10.88 11.45z"/>
                    </svg>
                </div>
                <p class="text-xs text-neutral-600 leading-relaxed">
                    Bu site TMDB API kullanmaktadir ancak TMDB tarafindan onaylanmamis veya sertifikalandirilmamistir.
                </p>
            </div>
        </div>

        {{-- Alt cizgi --}}
        <div class="border-t border-white/5 mt-8 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-xs text-neutral-600">&copy; {{ date('Y') }} BannerArchive. Tum haklari saklidir.</p>
            <p class="text-xs text-neutral-700">Laravel {{ app()->version() }} ile gelistirildi</p>
        </div>
    </div>
</footer>
