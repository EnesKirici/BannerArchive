<?php

/**
 * Livewire Particles Component
 *
 * YENİ KAVRAMLAR:
 * ---------------
 * 1. `wire:confirm` → Tıklamadan önce onay dialogu gösterir
 *    - <button wire:click="delete(1)" wire:confirm="Emin misiniz?">
 *
 * 2. `$this->dispatch('event')` → Tarayıcıya veya diğer component'lara event gönderir
 *
 * 3. `#[On('event')]` → Event listener — başka component'tan gelen event'i dinler
 *
 * 4. `wire:key` → Livewire'ın DOM diff'inde element'leri doğru eşleştirmesi için
 *    - Listedeki her item'a benzersiz key verilmeli
 */

use App\Models\ParticleTheme;
use App\Models\Setting;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('admin.layout')] #[Title('Particles Yönetimi')] class extends Component
{
    // Modal durumu
    public bool $showModal = false;
    public ?int $editingThemeId = null;

    // Form alanları
    public string $themeName = '';
    public string $themeColor = '#a855f7';
    public string $themeConfig = '';

    // Bat Animation ayarları
    public bool $batEnabled = false;
    public int $batCount = 5;
    public int $batSpeed = 20;
    public float $batScale = 2.0;
    public float $batFlapSpeed = 0.4;
    public string $batOuterColor = '#54556b';
    public string $batInnerColor = '#202020';

    public function mount(): void
    {
        $this->batEnabled = (bool) Setting::get('bat_animation_enabled', false);
        $this->batCount = (int) Setting::get('bat_animation_count', 5);
        $this->batSpeed = (int) Setting::get('bat_animation_speed', 20);
        $this->batScale = (float) Setting::get('bat_animation_scale', 2.0);
        $this->batFlapSpeed = (float) (Setting::get('bat_flap_speed', 0.4) ?? 0.4);
        $this->batOuterColor = Setting::get('bat_outer_color', '#54556b') ?? '#54556b';
        $this->batInnerColor = Setting::get('bat_inner_color', '#202020') ?? '#202020';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ParticleTheme>
     */
    #[Computed]
    public function themes(): \Illuminate\Database\Eloquent\Collection
    {
        return ParticleTheme::orderBy('is_preset', 'desc')->orderBy('name')->get();
    }

    #[Computed]
    public function activeTheme(): ?ParticleTheme
    {
        return ParticleTheme::active();
    }

    public function openCreateModal(): void
    {
        $this->editingThemeId = null;
        $this->themeName = '';
        $this->themeColor = '#a855f7';
        $this->themeConfig = json_encode([
            'fpsLimit' => 60,
            'particles' => [
                'number' => ['value' => 50],
                'color' => ['value' => '#a855f7'],
                'shape' => ['type' => 'circle'],
                'opacity' => ['value' => 0.5],
                'size' => ['value' => 3],
                'move' => ['enable' => true, 'speed' => 1],
            ],
            'interactivity' => [
                'events' => [
                    'onHover' => ['enable' => true, 'mode' => 'grab'],
                    'onClick' => ['enable' => true, 'mode' => 'push'],
                ],
            ],
            'detectRetina' => true,
        ], JSON_PRETTY_PRINT);
        $this->showModal = true;
    }

    public function openEditModal(int $themeId): void
    {
        $theme = ParticleTheme::findOrFail($themeId);
        $this->editingThemeId = $theme->id;
        $this->themeName = $theme->name;
        $this->themeColor = $theme->preview_color ?? '#a855f7';
        $this->themeConfig = json_encode($theme->config, JSON_PRETTY_PRINT);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingThemeId = null;
        $this->resetValidation();
    }

    public function saveTheme(): void
    {
        $this->validate([
            'themeName' => 'required|string|max:100',
            'themeConfig' => 'required|json',
            'themeColor' => 'nullable|string|max:20',
        ]);

        $config = json_decode($this->themeConfig, true);

        if ($this->editingThemeId) {
            $theme = ParticleTheme::findOrFail($this->editingThemeId);
            $theme->update([
                'name' => $this->themeName,
                'config' => $config,
                'preview_color' => $this->themeColor,
            ]);
            session()->flash('message', "'{$theme->name}' teması güncellendi.");
        } else {
            ParticleTheme::create([
                'name' => $this->themeName,
                'slug' => Str::slug($this->themeName),
                'config' => $config,
                'preview_color' => $this->themeColor,
                'is_preset' => false,
                'is_active' => false,
            ]);
            session()->flash('message', "'{$this->themeName}' teması oluşturuldu.");
        }

        $this->closeModal();
        unset($this->themes, $this->activeTheme);
    }

    public function activateTheme(int $themeId): void
    {
        $theme = ParticleTheme::findOrFail($themeId);
        $theme->activate();
        session()->flash('message', "'{$theme->name}' teması aktifleştirildi.");
        unset($this->themes, $this->activeTheme);
    }

    public function deactivateTheme(int $themeId): void
    {
        $theme = ParticleTheme::findOrFail($themeId);
        $theme->update(['is_active' => false]);
        session()->flash('message', "'{$theme->name}' teması devre dışı bırakıldı.");
        unset($this->themes, $this->activeTheme);
    }

    public function deleteTheme(int $themeId): void
    {
        $theme = ParticleTheme::findOrFail($themeId);

        if ($theme->is_preset) {
            session()->flash('error', 'Preset temalar silinemez.');
            return;
        }

        if ($theme->is_active) {
            session()->flash('error', 'Aktif tema silinemez. Önce başka bir temayı aktifleştirin.');
            return;
        }

        $name = $theme->name;
        $theme->delete();
        session()->flash('message', "'{$name}' teması silindi.");
        unset($this->themes);
    }

    public function seedPresets(): void
    {
        $presets = ParticleTheme::getDefaultPresets();
        $count = 0;

        foreach ($presets as $preset) {
            ParticleTheme::firstOrCreate(
                ['slug' => $preset['slug']],
                array_merge($preset, ['is_preset' => true])
            );
            $count++;
        }

        if (! ParticleTheme::active()) {
            ParticleTheme::where('slug', 'hexagons')->first()?->activate();
        }

        session()->flash('message', "{$count} preset tema yüklendi.");
        unset($this->themes, $this->activeTheme);
    }

    /**
     * Tema config'inden canvas preview parametreleri çıkarır
     *
     * @return array<string, mixed>
     */
    public function getPreviewParams(ParticleTheme $theme): array
    {
        $config = $theme->config;
        $shapeType = $config['particles']['shape']['type'] ?? 'circle';
        $colorVal = $config['particles']['color']['value'] ?? $theme->preview_color ?? '#a855f7';

        return [
            'color' => $theme->preview_color ?? '#a855f7',
            'colors' => is_array($colorVal) ? $colorVal : [$colorVal],
            'shape' => is_array($shapeType) ? $shapeType[0] : $shapeType,
            'sides' => $config['particles']['shape']['options']['polygon']['sides'] ?? 6,
            'points' => $config['particles']['shape']['options']['star']['sides'] ?? 5,
            'links' => $config['particles']['links']['enable'] ?? false,
            'direction' => $config['particles']['move']['direction'] ?? 'none',
            'speed' => $config['particles']['move']['speed'] ?? 1,
            'count' => min($config['particles']['number']['value'] ?? 30, 35),
            'wobble' => ($config['particles']['wobble']['enable'] ?? false),
        ];
    }

    public function saveBatAnimation(): void
    {
        $this->validate([
            'batCount' => 'required|integer|min:1|max:30',
            'batSpeed' => 'required|integer|min:5|max:60',
            'batScale' => 'required|numeric|min:0.5|max:6',
            'batFlapSpeed' => 'required|numeric|min:0.1|max:2',
            'batOuterColor' => 'required|string|max:20',
            'batInnerColor' => 'required|string|max:20',
        ]);

        Setting::set('bat_animation_enabled', $this->batEnabled ? '1' : '0', 'boolean', 'effects');
        Setting::set('bat_animation_count', (string) $this->batCount, 'integer', 'effects');
        Setting::set('bat_animation_speed', (string) $this->batSpeed, 'integer', 'effects');
        Setting::set('bat_animation_scale', (string) $this->batScale, 'string', 'effects');
        Setting::set('bat_flap_speed', (string) $this->batFlapSpeed, 'string', 'effects');
        Setting::set('bat_outer_color', $this->batOuterColor, 'string', 'effects');
        Setting::set('bat_inner_color', $this->batInnerColor, 'string', 'effects');

        session()->flash('message', 'Yarasa animasyonu ayarları kaydedildi.');
    }

    public function toggleBatAnimation(): void
    {
        $this->batEnabled = ! $this->batEnabled;
        Setting::set('bat_animation_enabled', $this->batEnabled ? '1' : '0', 'boolean', 'effects');

        session()->flash('message', $this->batEnabled
            ? 'Yarasa animasyonu aktifleştirildi.'
            : 'Yarasa animasyonu devre dışı bırakıldı.');
    }

};
?>

<div>
    {{-- Flash Mesajlar --}}
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Üst Bar --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-neutral-400">Background efektlerini buradan yönetebilirsiniz.</p>
        <button wire:click="seedPresets" wire:loading.attr="disabled"
                class="px-4 py-2 bg-neutral-800 hover:bg-neutral-700 disabled:opacity-50 text-white rounded-lg transition-colors text-sm">
            <span wire:loading wire:target="seedPresets">Yükleniyor...</span>
            <span wire:loading.remove wire:target="seedPresets">Preset'leri Yükle</span>
        </button>
    </div>

    {{-- Tema Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        @forelse($this->themes as $theme)
        {{-- wire:key → Her list item'a benzersiz key verilmeli --}}
        <div wire:key="theme-{{ $theme->id }}"
             class="bg-neutral-900 rounded-xl border {{ $theme->is_active ? 'border-fuchsia-500' : 'border-white/5' }} overflow-hidden group">
            {{-- Preview --}}
            @php $pp = $this->getPreviewParams($theme); @endphp
            @if(($theme->config['renderer'] ?? null) === 'spider')
            <div class="h-36 relative overflow-hidden bg-neutral-950"
                 x-data="spiderPreview('{{ $theme->config['color'] ?? '#ffffff' }}')" x-init="init()">
                <canvas x-ref="spiderCanvas" class="w-full h-full"></canvas>
            @else
            <div class="h-36 relative overflow-hidden bg-neutral-950"
                 x-data="particlePreview({{ Js::from($pp) }})" x-on:remove.window="destroy()">
                <canvas x-ref="canvas" class="w-full h-full"></canvas>
            @endif
                @if($theme->is_active)
                <div class="absolute top-3 right-3 px-2 py-1 bg-fuchsia-500 text-white text-[10px] font-bold rounded-md uppercase tracking-wider">
                    Aktif
                </div>
                @endif
                @if($theme->is_preset)
                <div class="absolute top-3 left-3 px-2 py-0.5 bg-white/10 backdrop-blur-sm text-neutral-300 text-[10px] rounded-md">
                    Preset
                </div>
                @endif
            </div>

            {{-- Info --}}
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-3 h-3 rounded-full" style="background: {{ $theme->preview_color }}"></div>
                        <h3 class="font-semibold text-sm">{{ $theme->name }}</h3>
                    </div>
                    <div class="flex items-center gap-1">
                        <button wire:click="openEditModal({{ $theme->id }})"
                                class="p-1.5 text-neutral-500 hover:text-white hover:bg-white/5 rounded-lg transition-colors"
                                title="Düzenle">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </button>
                        @if(!$theme->is_preset && !$theme->is_active)
                        <button wire:click="deleteTheme({{ $theme->id }})"
                                wire:confirm="Bu temayı silmek istediğinizden emin misiniz?"
                                class="p-1.5 text-neutral-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                                title="Sil">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>

                @if(!$theme->is_active)
                <button wire:click="activateTheme({{ $theme->id }})"
                        wire:loading.attr="disabled"
                        class="w-full px-3 py-1.5 bg-white/5 hover:bg-fuchsia-600 disabled:opacity-50 text-neutral-400 hover:text-white text-xs font-medium rounded-lg transition-all">
                    Aktifleştir
                </button>
                @else
                <button wire:click="deactivateTheme({{ $theme->id }})"
                        class="w-full px-3 py-1.5 bg-fuchsia-500/10 text-fuchsia-400 hover:bg-red-500/10 hover:text-red-400 text-xs font-medium rounded-lg text-center transition-all">
                    Devre Dışı Bırak
                </button>
                @endif
            </div>
        </div>
        @empty
        <div class="col-span-full bg-neutral-900 rounded-xl border border-white/5 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-neutral-700 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            <p class="text-neutral-500 mb-4">Henüz tema yok.</p>
            <button wire:click="seedPresets" class="px-6 py-2 bg-fuchsia-600 hover:bg-fuchsia-500 text-white rounded-lg transition-colors">
                Preset Temaları Yükle
            </button>
        </div>
        @endforelse
    </div>

    {{-- Yeni Tema Butonu --}}
    <button wire:click="openCreateModal"
            class="w-full py-4 border-2 border-dashed border-neutral-800 hover:border-fuchsia-500/50 rounded-xl text-neutral-500 hover:text-fuchsia-400 transition-colors">
        + Yeni Tema Oluştur
    </button>

    {{-- ═══ ANİMASYONLAR BÖLÜMÜ ═══ --}}
    <div class="mt-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-neutral-400">Animasyonlar</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="bg-neutral-900 rounded-xl border {{ $batEnabled ? 'border-purple-500' : 'border-white/5' }} overflow-hidden"
                 x-data="{ showSettings: false }">
                {{-- Preview --}}
                <div class="h-36 relative overflow-hidden bg-neutral-950">
                    @if($batEnabled)
                        <link rel="stylesheet" href="{{ asset('css/bat-animation.css') }}">
                        <div class="bat-animation-container" style="position:relative; width:100%; height:100%;">
                            <div class="bat" style="animation: 0.4s bat steps(1) infinite; transform: scale(1.2); top: 20%; left: 25%;"></div>
                            <div class="bat" style="animation: 0.4s bat steps(1) infinite; transform: scale(0.8); top: 50%; left: 60%; opacity: 0.6;"></div>
                        </div>
                    @else
                        <div class="flex items-center justify-center h-full text-neutral-700">
                            <svg class="w-10 h-10" viewBox="0 0 24 24" fill="currentColor" opacity="0.3">
                                <path d="M22.39 12.39c-.1-.1-.21-.18-.33-.24a1.09 1.09 0 00-.78-.05c-.13.04-.25.11-.36.2L19 14.17V10c0-.28-.11-.53-.29-.71L15.5 6.08l.71-.71a1 1 0 000-1.41 1 1 0 00-1.41 0L12 6.76 9.21 3.96a1 1 0 00-1.41 0 1 1 0 000 1.41l.71.71L5.29 9.29A1 1 0 005 10v4.17L3.08 12.3a.87.87 0 00-.36-.2 1.09 1.09 0 00-.78.05c-.12.06-.23.14-.33.24a1 1 0 000 1.41l3.68 3.68A4.97 4.97 0 009 19h6a4.97 4.97 0 003.71-1.52l3.68-3.68a1 1 0 000-1.41z"/>
                            </svg>
                        </div>
                    @endif
                    @if($batEnabled)
                    <div class="absolute top-3 right-3 px-2 py-1 bg-purple-500 text-white text-[10px] font-bold rounded-md uppercase tracking-wider">
                        Aktif
                    </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-3 h-3 rounded-full" style="background: {{ $batOuterColor }}"></div>
                            <h3 class="font-semibold text-sm">Pixel Bat</h3>
                        </div>
                        @if($batEnabled)
                        <button @click="showSettings = !showSettings"
                                class="p-1.5 text-neutral-500 hover:text-white hover:bg-white/5 rounded-lg transition-colors"
                                title="Ayarlar">
                            <svg class="w-3.5 h-3.5 transition-transform" :class="showSettings && 'rotate-45'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </button>
                        @endif
                    </div>

                    @if(!$batEnabled)
                    <button wire:click="toggleBatAnimation"
                            class="w-full px-3 py-1.5 bg-white/5 hover:bg-purple-600 text-neutral-400 hover:text-white text-xs font-medium rounded-lg transition-all">
                        Aktifleştir
                    </button>
                    @else
                    <button wire:click="toggleBatAnimation"
                            class="w-full px-3 py-1.5 bg-purple-500/10 text-purple-400 hover:bg-red-500/10 hover:text-red-400 text-xs font-medium rounded-lg text-center transition-all">
                        Devre Dışı Bırak
                    </button>
                    @endif
                </div>

                {{-- Ayarlar Accordion --}}
                @if($batEnabled)
                <div x-show="showSettings" x-cloak
                     x-transition:enter="transition-all ease-out duration-200"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-[600px]"
                     x-transition:leave="transition-all ease-in duration-150"
                     x-transition:leave-start="opacity-100 max-h-[600px]"
                     x-transition:leave-end="opacity-0 max-h-0"
                     class="overflow-hidden">
                    <div class="px-4 pb-4 space-y-4 border-t border-white/5 pt-4">
                        {{-- Renkler --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-neutral-500 mb-1">Dış Renk (Kanat)</label>
                                <div class="flex gap-1.5">
                                    <input type="color" wire:model="batOuterColor"
                                           class="w-8 h-8 rounded cursor-pointer bg-transparent border-0 shrink-0">
                                    <input type="text" wire:model="batOuterColor"
                                           class="flex-1 min-w-0 px-2 py-1.5 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-purple-500 font-mono text-xs">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase tracking-wider text-neutral-500 mb-1">İç Renk (Gövde)</label>
                                <div class="flex gap-1.5">
                                    <input type="color" wire:model="batInnerColor"
                                           class="w-8 h-8 rounded cursor-pointer bg-transparent border-0 shrink-0">
                                    <input type="text" wire:model="batInnerColor"
                                           class="flex-1 min-w-0 px-2 py-1.5 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-purple-500 font-mono text-xs">
                                </div>
                            </div>
                        </div>

                        {{-- Sayı --}}
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-neutral-500 mb-1">Yarasa Sayısı</label>
                            <input type="number" wire:model="batCount" min="1" max="30"
                                   class="w-full px-3 py-1.5 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-purple-500 font-mono text-xs">
                        </div>

                        {{-- Boyut --}}
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-neutral-500 mb-1">Boyut — {{ $batScale }}x</label>
                            <input type="range" wire:model="batScale" min="0.5" max="6" step="0.5"
                                   class="w-full accent-purple-500">
                        </div>

                        {{-- Uçuş Hızı --}}
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-neutral-500 mb-1">Uçuş Hızı — {{ $batSpeed }}s</label>
                            <input type="range" wire:model="batSpeed" min="5" max="60" step="1"
                                   class="w-full accent-purple-500">
                        </div>

                        {{-- Kanat Çırpma Hızı --}}
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-neutral-500 mb-1">Kanat Çırpma Hızı — {{ $batFlapSpeed }}s</label>
                            <input type="range" wire:model="batFlapSpeed" min="0.1" max="2" step="0.1"
                                   class="w-full accent-purple-500">
                            <p class="text-[10px] text-neutral-400 mt-0.5">Düşük = hızlı çırpma, yüksek = yavaş çırpma</p>
                        </div>

                        {{-- Kaydet & Devre Dışı --}}
                        <div class="flex gap-2 pt-1">
                            <button wire:click="saveBatAnimation" wire:loading.attr="disabled"
                                    class="flex-1 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-medium rounded-lg transition-colors">
                                <span wire:loading wire:target="saveBatAnimation">Kaydediliyor...</span>
                                <span wire:loading.remove wire:target="saveBatAnimation">Kaydet</span>
                            </button>
                            <button wire:click="toggleBatAnimation"
                                    class="px-4 py-2 bg-red-500/10 text-red-400 hover:bg-red-500/20 text-xs font-medium rounded-lg transition-colors">
                                Kapat
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 z-50" x-data x-on:keydown.escape.window="$wire.closeModal()">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" wire:click="closeModal"></div>
        <div class="absolute inset-4 md:inset-10 lg:inset-20 bg-neutral-900 rounded-2xl border border-white/10 flex flex-col overflow-hidden">
            {{-- Modal Header --}}
            <div class="p-6 border-b border-white/5 flex items-center justify-between">
                <h2 class="text-xl font-bold">
                    {{ $editingThemeId ? 'Tema Düzenle' : 'Yeni Tema Oluştur' }}
                </h2>
                <button wire:click="closeModal" class="p-2 hover:bg-white/5 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 flex overflow-hidden">
                {{-- Editor --}}
                <div class="w-1/2 p-6 overflow-auto border-r border-white/5">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-neutral-300 mb-2">Tema Adı</label>
                            <input type="text" wire:model="themeName"
                                   class="w-full px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500">
                            @error('themeName') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-neutral-300 mb-2">Önizleme Rengi</label>
                            <div class="flex gap-2">
                                <input type="color" wire:model.live="themeColor"
                                       class="w-12 h-10 rounded cursor-pointer bg-transparent border-0">
                                <input type="text" wire:model.live="themeColor"
                                       class="flex-1 px-4 py-2 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 font-mono text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-neutral-300 mb-2">Konfigürasyon (JSON)</label>
                            <textarea wire:model="themeConfig" rows="20"
                                      class="w-full px-4 py-3 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 font-mono text-sm resize-none"></textarea>
                            @error('themeConfig') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button wire:click="saveTheme" wire:loading.attr="disabled"
                                class="flex-1 py-3 bg-fuchsia-600 hover:bg-fuchsia-500 disabled:opacity-50 text-white font-semibold rounded-lg transition-colors">
                            <span wire:loading wire:target="saveTheme">Kaydediliyor...</span>
                            <span wire:loading.remove wire:target="saveTheme">Kaydet</span>
                        </button>
                        <button wire:click="closeModal"
                                class="px-6 py-3 bg-neutral-800 hover:bg-neutral-700 text-white rounded-lg transition-colors">
                            İptal
                        </button>
                    </div>
                </div>

                {{-- Preview --}}
                <div class="w-1/2 bg-neutral-950 relative overflow-hidden"
                     x-data="particlePreview({ color: $wire.themeColor || '#a855f7', colors: [$wire.themeColor || '#a855f7'], shape: 'circle', sides: 6, points: 5, links: true, direction: 'none', speed: 1, count: 50, wobble: false })"
                     x-on:remove.window="destroy()">
                    <canvas x-ref="canvas" class="w-full h-full"></canvas>
                    <div class="absolute bottom-4 left-4 text-xs text-neutral-600">
                        <span class="inline-block w-2 h-2 rounded-full mr-1" style="background: {{ $themeColor }}"></span>
                        {{ $themeColor }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
