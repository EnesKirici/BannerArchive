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
            <div class="h-32 relative" style="background: linear-gradient(135deg, {{ $theme->preview_color }}20, transparent)">
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="w-16 h-16 rounded-full opacity-30" style="background: {{ $theme->preview_color }}; filter: blur(20px);"></div>
                </div>
                @if($theme->is_active)
                <div class="absolute top-3 right-3 px-2 py-1 bg-fuchsia-500 text-white text-xs font-bold rounded">
                    AKTİF
                </div>
                @endif
                @if($theme->is_preset)
                <div class="absolute top-3 left-3 px-2 py-1 bg-neutral-800 text-neutral-400 text-xs rounded">
                    Preset
                </div>
                @endif
            </div>

            {{-- Info --}}
            <div class="p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-4 h-4 rounded-full" style="background: {{ $theme->preview_color }}"></div>
                    <h3 class="font-semibold">{{ $theme->name }}</h3>
                </div>

                <div class="flex items-center gap-2">
                    @if(!$theme->is_active)
                    <button wire:click="activateTheme({{ $theme->id }})"
                            wire:loading.attr="disabled"
                            class="flex-1 px-3 py-2 bg-fuchsia-600 hover:bg-fuchsia-500 disabled:opacity-50 text-white text-sm rounded-lg transition-colors">
                        Aktifleştir
                    </button>
                    @else
                    <span class="flex-1 px-3 py-2 bg-neutral-800 text-neutral-500 text-sm rounded-lg text-center cursor-not-allowed">
                        Aktif
                    </span>
                    @endif

                    {{-- wire:click → PHP metodu çağırır --}}
                    <button wire:click="openEditModal({{ $theme->id }})"
                            class="px-3 py-2 bg-neutral-800 hover:bg-neutral-700 text-white text-sm rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </button>

                    @if(!$theme->is_preset && !$theme->is_active)
                    {{-- wire:confirm → Tıklamadan önce onay dialogu gösterir --}}
                    <button wire:click="deleteTheme({{ $theme->id }})"
                            wire:confirm="Bu temayı silmek istediğinizden emin misiniz?"
                            class="px-3 py-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white text-sm rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    @endif
                </div>
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
                <div class="w-1/2 bg-neutral-950 relative flex items-center justify-center">
                    <div class="text-center text-neutral-600">
                        <div class="w-24 h-24 rounded-full mx-auto mb-4 opacity-50" style="background: {{ $themeColor }}; filter: blur(30px);"></div>
                        <p class="text-sm">Renk Önizleme</p>
                        <p class="text-xs text-neutral-700 mt-1">{{ $themeColor }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
