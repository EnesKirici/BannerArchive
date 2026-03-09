<?php

/**
 * Livewire Cache Manager Component
 *
 * YENİ KAVRAMLAR:
 * ---------------
 * 1. `wire:confirm` → Tehlikeli işlemlerden önce kullanıcıdan onay alır
 *
 * 2. `#[Computed]` ile hesaplanmış veri → Her render'da güncel veri
 *
 * 3. Birden fazla metod → Her buton farklı bir PHP metodu çağırabilir
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('admin.layout')] #[Title('Cache Yönetimi')] class extends Component
{
    public string $lastAction = '';

    /**
     * @return array<string, array{label: string, description: string, count: int|string}>
     */
    #[Computed]
    public function cacheInfo(): array
    {
        $quotesCount = 0;

        // Cache'deki quotes sayısını hesapla (file driver için)
        if (config('cache.default') === 'file') {
            $cachePath = storage_path('framework/cache/data');
            if (is_dir($cachePath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($cachePath, \FilesystemIterator::SKIP_DOTS)
                );
                $quotesCount = iterator_count($iterator);
            }
        }

        return [
            'driver' => [
                'label' => 'Cache Driver',
                'description' => 'Kullanılan cache mekanizması',
                'count' => config('cache.default'),
            ],
            'quotes' => [
                'label' => 'AI Sözleri Cache',
                'description' => 'Gemini API ile üretilmiş banner sözleri',
                'count' => $quotesCount . ' dosya',
            ],
            'views' => [
                'label' => 'View Cache',
                'description' => 'Derlenmiş Blade şablonları',
                'count' => count(glob(storage_path('framework/views/*.php')) ?: []) . ' dosya',
            ],
            'config' => [
                'label' => 'Config Cache',
                'description' => 'Derlenmiş uygulama yapılandırması',
                'count' => file_exists(base_path('bootstrap/cache/config.php')) ? 'Aktif' : 'Yok',
            ],
            'routes' => [
                'label' => 'Route Cache',
                'description' => 'Derlenmiş rota tablosu',
                'count' => file_exists(base_path('bootstrap/cache/routes-v7.php')) ? 'Aktif' : 'Yok',
            ],
        ];
    }

    public function clearApplicationCache(): void
    {
        Artisan::call('cache:clear');
        $this->lastAction = 'Uygulama cache temizlendi.';
        unset($this->cacheInfo);
    }

    public function clearViewCache(): void
    {
        Artisan::call('view:clear');
        $this->lastAction = 'View cache temizlendi.';
        unset($this->cacheInfo);
    }

    public function clearConfigCache(): void
    {
        Artisan::call('config:clear');
        $this->lastAction = 'Config cache temizlendi.';
        unset($this->cacheInfo);
    }

    public function clearRouteCache(): void
    {
        Artisan::call('route:clear');
        $this->lastAction = 'Route cache temizlendi.';
        unset($this->cacheInfo);
    }

    public function clearAllCache(): void
    {
        Artisan::call('optimize:clear');
        $this->lastAction = 'Tüm cache temizlendi (config, routes, views, events, cache).';
        unset($this->cacheInfo);
    }

    public function optimizeApplication(): void
    {
        Artisan::call('optimize');
        $this->lastAction = 'Uygulama optimize edildi (config, routes cache oluşturuldu).';
        unset($this->cacheInfo);
    }

};
?>

<div>
    {{-- Başarı Mesajı --}}
    @if($lastAction !== '')
        <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ $lastAction }}
        </div>
    @endif

    {{-- Cache Bilgileri Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        @foreach($this->cacheInfo as $key => $info)
        <div wire:key="cache-{{ $key }}" class="bg-neutral-900 rounded-xl border border-white/5 p-6">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="font-semibold">{{ $info['label'] }}</h3>
                    <p class="text-xs text-neutral-500 mt-1">{{ $info['description'] }}</p>
                </div>
                <span class="px-2.5 py-1 bg-neutral-800 text-neutral-300 text-xs font-mono rounded">
                    {{ $info['count'] }}
                </span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Aksiyonlar --}}
    <div class="bg-neutral-900 rounded-xl border border-white/5 divide-y divide-white/5">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-1">Cache Temizleme</h3>
            <p class="text-sm text-neutral-500">Belirli cache türlerini veya tümünü temizleyin.</p>
        </div>

        {{-- Her satır bir temizleme aksiyonu --}}
        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="font-medium">Uygulama Cache</p>
                <p class="text-sm text-neutral-500">Tüm uygulama cache verilerini temizler (quotes dahil)</p>
            </div>
            <button wire:click="clearApplicationCache"
                    wire:confirm="Uygulama cache'i temizlenecek. AI sözleri cache'i de silinecek. Emin misiniz?"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-50 text-white text-sm rounded-lg transition-colors whitespace-nowrap">
                <span wire:loading wire:target="clearApplicationCache">Temizleniyor...</span>
                <span wire:loading.remove wire:target="clearApplicationCache">Temizle</span>
            </button>
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="font-medium">View Cache</p>
                <p class="text-sm text-neutral-500">Derlenmiş Blade şablonlarını temizler</p>
            </div>
            <button wire:click="clearViewCache" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-50 text-white text-sm rounded-lg transition-colors whitespace-nowrap">
                <span wire:loading wire:target="clearViewCache">Temizleniyor...</span>
                <span wire:loading.remove wire:target="clearViewCache">Temizle</span>
            </button>
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="font-medium">Config Cache</p>
                <p class="text-sm text-neutral-500">Derlenmiş yapılandırma dosyasını temizler</p>
            </div>
            <button wire:click="clearConfigCache" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-50 text-white text-sm rounded-lg transition-colors whitespace-nowrap">
                <span wire:loading wire:target="clearConfigCache">Temizleniyor...</span>
                <span wire:loading.remove wire:target="clearConfigCache">Temizle</span>
            </button>
        </div>

        <div class="p-6 flex items-center justify-between">
            <div>
                <p class="font-medium">Route Cache</p>
                <p class="text-sm text-neutral-500">Derlenmiş rota tablosunu temizler</p>
            </div>
            <button wire:click="clearRouteCache" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-50 text-white text-sm rounded-lg transition-colors whitespace-nowrap">
                <span wire:loading wire:target="clearRouteCache">Temizleniyor...</span>
                <span wire:loading.remove wire:target="clearRouteCache">Temizle</span>
            </button>
        </div>
    </div>

    {{-- Toplu İşlemler --}}
    <div class="mt-6 flex flex-col sm:flex-row gap-4">
        <button wire:click="clearAllCache"
                wire:confirm="TÜM cache verileri temizlenecek. Emin misiniz?"
                wire:loading.attr="disabled"
                class="flex-1 py-3 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white disabled:opacity-50 font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            <span wire:loading wire:target="clearAllCache">Temizleniyor...</span>
            <span wire:loading.remove wire:target="clearAllCache">Tümünü Temizle</span>
        </button>

        <button wire:click="optimizeApplication"
                wire:loading.attr="disabled"
                class="flex-1 py-3 bg-fuchsia-600 hover:bg-fuchsia-500 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <span wire:loading wire:target="optimizeApplication">Optimize ediliyor...</span>
            <span wire:loading.remove wire:target="optimizeApplication">Optimize Et</span>
        </button>
    </div>
</div>
