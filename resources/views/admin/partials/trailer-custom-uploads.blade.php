{{--
    Kapak Stüdyosu — kendi görselini yükleme kutuları.

    İki yerde kullanılır: elle modda (kapak türü kartında) ve ayarlar panelinde
    (TMDB seçiliyken de). Kutular trailer-studio bileşeninin posterUpload /
    backdropUpload özelliklerine bağlıdır; dosya diske alınınca customPoster /
    customBackdrop dolar ve önizleme buradan gösterilir.
--}}
@php
    $kutular = [
        'poster' => [
            'upload' => 'posterUpload',
            'stored' => $customPoster,
            'baslik' => 'Afiş',
            'aciklama' => 'dikey, 2:3 · Shorts zemini ve afiş kartı',
            'oran' => 'aspect-[2/3]',
            'genislik' => 'max-w-[150px]',
        ],
        'backdrop' => [
            'upload' => 'backdropUpload',
            'stored' => $customBackdrop,
            'baslik' => 'Arka plan',
            'aciklama' => 'yatay, 16:9 · video kapağının zemini',
            'oran' => 'aspect-video',
            'genislik' => 'max-w-[260px]',
        ],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    @foreach($kutular as $tur => $kutu)
        <div wire:key="ozel-{{ $tur }}" class="rounded-xl border border-dashed p-3 transition-colors {{ $kutu['stored'] ? 'border-fuchsia-500/50 bg-fuchsia-500/5' : 'border-white/15 hover:border-white/30' }}">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-medium">
                    {{ $kutu['baslik'] }}
                    <span class="block text-[11px] text-neutral-500 font-normal">{{ $kutu['aciklama'] }}</span>
                </p>
                @if($kutu['stored'])
                    <button type="button" wire:click="removeCustom('{{ $tur }}')"
                            class="shrink-0 px-2 py-1 text-[11px] rounded bg-white/5 hover:bg-red-500/20 hover:text-red-300 transition-colors">
                        Kaldır
                    </button>
                @endif
            </div>

            <label class="mt-3 block cursor-pointer">
                <input type="file" wire:model="{{ $kutu['upload'] }}" accept="image/png,image/jpeg,image/webp" class="hidden">

                <span wire:loading.remove wire:target="{{ $kutu['upload'] }}" class="block">
                    @if($kutu['stored'])
                        <span class="block {{ $kutu['oran'] }} {{ $kutu['genislik'] }} mx-auto rounded-lg overflow-hidden border border-white/10 bg-neutral-950">
                            <img src="{{ route('admin.trailers.preview', ['file' => basename($kutu['stored']), 'v' => @filemtime($kutu['stored']) ?: 0]) }}"
                                 alt="{{ $kutu['baslik'] }}" class="w-full h-full object-cover">
                        </span>
                        <span class="mt-2 block text-center text-[11px] text-neutral-500">Değiştirmek için tıklayın</span>
                    @else
                        <span class="flex flex-col items-center justify-center gap-1.5 {{ $kutu['oran'] }} {{ $kutu['genislik'] }} mx-auto rounded-lg bg-neutral-950 border border-white/5 text-neutral-500 text-xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/>
                            </svg>
                            Görsel seç
                            <span class="text-[10px] text-neutral-600">JPG · PNG · WebP · en çok 15 MB</span>
                        </span>
                    @endif
                </span>

                <span wire:loading.flex wire:target="{{ $kutu['upload'] }}"
                      class="{{ $kutu['oran'] }} {{ $kutu['genislik'] }} mx-auto rounded-lg bg-neutral-950 border border-white/5 items-center justify-center gap-2 text-xs text-neutral-300">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Yükleniyor…
                </span>
            </label>

            @error($kutu['upload'])
                <p class="mt-2 text-[11px] text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
</div>
