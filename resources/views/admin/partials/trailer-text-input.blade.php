{{--
    Kapak Stüdyosu — "Uygula" butonlu metin kutusu.

    Değer odak kaybında, Enter'da ya da Uygula'ya basınca sunucuya gider ve
    kapaklar tazelenir. Livewire 4'te düz .blur ağ isteği atmadığı için
    .live.blur kullanılır. Kaydedilmemiş değişiklik varken buton vurgulanır.

    Parametreler: $model (bileşen özelliği), $placeholder.
--}}
<div class="flex gap-2">
    <input wire:model.live.blur="{{ $model }}" wire:keydown.enter.prevent="$commit"
           type="text" placeholder="{{ $placeholder }}"
           class="min-w-0 flex-1 bg-neutral-950 border border-white/10 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-fuchsia-500/60">
    <button type="button" wire:click="$commit"
            wire:loading.attr="disabled" wire:target="{{ $model }}"
            wire:dirty.class="!border-fuchsia-500 !bg-fuchsia-500/15 !text-fuchsia-300"
            class="shrink-0 px-3 py-2 rounded-lg border border-white/10 text-sm font-medium text-neutral-300 transition-colors hover:border-fuchsia-500/60 hover:text-fuchsia-300 disabled:opacity-50">
        Uygula
    </button>
</div>
