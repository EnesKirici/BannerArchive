<?php

/**
 * Livewire Settings Component
 *
 * LIVEWIRE KAVRAMLARI:
 * --------------------
 * 1. `public` property'ler = Blade'de otomatik erişilebilir (two-way binding)
 *    - `wire:model="siteName"` → PHP'deki $siteName ile senkron kalır
 *
 * 2. `mount()` = Component ilk yüklendiğinde çalışır (constructor gibi)
 *
 * 3. `wire:click="save"` = Butona tıklayınca PHP'deki save() metodunu çağırır
 *    - Sayfa yenilenmez! AJAX ile arka planda çalışır.
 *
 * 4. `wire:model.live` = Her tuş vuruşunda anında günceller (debounce olmadan)
 *    - `wire:model.blur` = Input'tan çıkınca günceller
 *    - `wire:model` = Form submit edilince günceller (varsayılan)
 *
 * 5. `wire:loading` = İşlem devam ederken gösterilecek element
 */

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('admin.layout')] #[Title('Ayarlar')] class extends Component
{
    // ═══ PUBLIC PROPERTY'LER ═══
    // Blade'de {{ $siteName }} veya wire:model="siteName" ile erişilir
    public string $siteName = '';
    public string $siteDescription = '';
    public string $tmdbApiKey = '';
    public string $primaryColor = '#d946ef';
    public string $geminiApiKey = '';
    public string $galleryViewMode = 'gallery';
    public string $particlesLayer = 'background';

    // ═══ MOUNT (Component ilk yüklendiğinde) ═══
    public function mount(): void
    {
        // Veritabanından mevcut ayarları yükle
        $this->siteName = (string) Setting::get('site_name', 'BannerArchive');
        $this->siteDescription = (string) Setting::get('site_description', 'Film ve dizi banner arşivi');
        $this->tmdbApiKey = (string) Setting::get('tmdb_api_key', '');
        $this->primaryColor = (string) Setting::get('primary_color', '#d946ef');
        $this->geminiApiKey = (string) Setting::get('gemini_api_key', '');
        $this->galleryViewMode = (string) Setting::get('gallery_view_mode', 'gallery');
        $this->particlesLayer = (string) Setting::get('particles_layer', 'background');
    }

    // ═══ SAVE METODU ═══
    // wire:click="save" ile Blade'den çağrılır
    public function save(): void
    {
        // Validasyon — Livewire'da $this->validate() kullanılır
        $this->validate([
            'siteName' => 'required|string|max:100',
            'siteDescription' => 'required|string|max:500',
            'tmdbApiKey' => 'nullable|string|max:200',
            'primaryColor' => 'required|string|max:20',
            'geminiApiKey' => 'nullable|string|max:200',
            'galleryViewMode' => 'required|in:modal,gallery,both',
            'particlesLayer' => 'required|in:overlay,background',
        ]);

        // Veritabanına kaydet
        Setting::set('site_name', $this->siteName, 'string', 'general');
        Setting::set('site_description', $this->siteDescription, 'string', 'general');
        Setting::set('tmdb_api_key', $this->tmdbApiKey, 'string', 'api');
        Setting::set('primary_color', $this->primaryColor, 'string', 'appearance');
        Setting::set('gemini_api_key', $this->geminiApiKey, 'string', 'api');
        Setting::set('gallery_view_mode', $this->galleryViewMode, 'string', 'appearance');
        Setting::set('particles_layer', $this->particlesLayer, 'string', 'appearance');

        // ═══ FLASH MESSAGE ═══
        // session()->flash() → Blade'de @if(session('message')) ile gösterilir
        session()->flash('message', 'Ayarlar başarıyla kaydedildi.');
    }

};
?>

{{-- ═══ BLADE TEMPLATE ═══ --}}
{{-- Livewire component'larında tüm HTML tek bir root <div> içinde olmalı --}}
<div>
    {{-- Flash mesaj gösterimi --}}
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Validasyon hataları --}}
    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Sol: Genel Ayarlar --}}
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Genel Ayarlar
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Site Adı</label>
                    <input type="text" wire:model.blur="siteName"
                           class="w-full px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 transition-colors">
                    @error('siteName') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Site Açıklaması</label>
                    <textarea wire:model.blur="siteDescription" rows="3"
                              class="w-full px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 resize-none transition-colors"></textarea>
                    @error('siteDescription') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Birincil Renk</label>
                    <div class="flex gap-3 items-center">
                        <input type="color" wire:model.live="primaryColor"
                               class="w-12 h-10 rounded cursor-pointer bg-transparent border-0">
                        <input type="text" wire:model.live="primaryColor"
                               class="w-32 px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg font-mono text-sm focus:outline-none focus:border-fuchsia-500 transition-colors">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg" style="background: {{ $primaryColor }}"></div>
                            <span class="text-xs text-neutral-500">Önizleme</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sağ: API Ayarları --}}
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                API Ayarları
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">TMDB API Key</label>
                    <input type="text" wire:model.blur="tmdbApiKey"
                           class="w-full px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 font-mono text-sm transition-colors"
                           placeholder="API anahtarınızı girin...">
                    <p class="mt-1 text-xs text-neutral-500">TMDB'den ücretsiz API anahtarı alabilirsiniz</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Gemini API Key</label>
                    <input type="text" wire:model.blur="geminiApiKey"
                           class="w-full px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 font-mono text-sm transition-colors"
                           placeholder="Gemini API anahtarınızı girin...">
                    <p class="mt-1 text-xs text-neutral-500">AI söz üretimi için Google Gemini API anahtarı</p>
                </div>
            </div>
        </div>

        {{-- Tam genişlik: Görünüm Ayarları --}}
        <div class="lg:col-span-2 bg-neutral-900 rounded-xl border border-white/5 p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                </svg>
                Görünüm Ayarları
            </h3>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Görsel Görüntüleme Modu</label>
                    <p class="text-xs text-neutral-500 mb-3">Film/dizi kartına tıklandığında görsellerin nasıl açılacağını belirler</p>
                    <div class="space-y-3">
                        @foreach ([
                            'modal' => ['Sadece Modal', 'Hızlı önizleme modal\'ı açılır', 'M6 18L18 6M6 6l12 12'],
                            'gallery' => ['Sadece Galeri', 'Tam sayfa galeri sayfasına gider', 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
                            'both' => ['Her İkisi', 'Modal açılır + galeri butonu görünür', 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7'],
                        ] as $value => [$label, $desc, $iconPath])
                            <label class="cursor-pointer block">
                                <input type="radio" wire:model.live="galleryViewMode" value="{{ $value }}" class="hidden peer">
                                <div class="p-3 rounded-xl border-2 transition-all peer-checked:border-fuchsia-500 peer-checked:bg-fuchsia-500/10 border-neutral-700 hover:border-neutral-600">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <svg class="w-4 h-4 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/>
                                        </svg>
                                        <span class="text-sm font-semibold text-white">{{ $label }}</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-400 pl-6">{{ $desc }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('galleryViewMode') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-300 mb-2">Particles Katmanı</label>
                    <p class="text-xs text-neutral-500 mb-3">Yıldız efektlerinin içerik ile olan katman ilişkisini belirler</p>
                    <div class="space-y-3">
                        @foreach ([
                            'overlay' => ['Ön Plan', 'Yıldızlar içeriğin üzerinde görünür', 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
                            'background' => ['Arka Plan', 'Yıldızlar sadece grid arkasında görünür', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                        ] as $value => [$label, $desc, $iconPath])
                            <label class="cursor-pointer block">
                                <input type="radio" wire:model.live="particlesLayer" value="{{ $value }}" class="hidden peer">
                                <div class="p-3 rounded-xl border-2 transition-all peer-checked:border-fuchsia-500 peer-checked:bg-fuchsia-500/10 border-neutral-700 hover:border-neutral-600">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <svg class="w-4 h-4 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/>
                                        </svg>
                                        <span class="text-sm font-semibold text-white">{{ $label }}</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-400 pl-6">{{ $desc }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('particlesLayer') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Kaydet Butonu --}}
    <div class="mt-6 flex items-center gap-4">
        <button wire:click="save"
                wire:loading.attr="disabled"
                class="px-6 py-3 bg-fuchsia-600 hover:bg-fuchsia-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-colors flex items-center gap-2">
            <span wire:loading wire:target="save">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </span>
            <span wire:loading.remove wire:target="save">Ayarları Kaydet</span>
            <span wire:loading wire:target="save">Kaydediliyor...</span>
        </button>
    </div>
</div>
