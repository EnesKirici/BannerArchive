<?php

/**
 * Livewire Dashboard Component
 *
 * YENİ KAVRAMLAR:
 * ---------------
 * 1. `#[Computed]` → Hesaplanmış özellik, $this->stats ile erişilir
 *    Component her render edildiğinde yeniden hesaplanır ama aynı render
 *    döngüsünde cache'lenir (aynı property'e 2 kez erişirsen 1 kez SQL çalışır)
 */

use App\Models\LoginHistory;
use App\Models\ParticleTheme;
use App\Models\Setting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('admin.layout')] #[Title('Dashboard')] class extends Component
{
    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total_logins' => LoginHistory::where('success', true)->count(),
            'failed_logins' => LoginHistory::where('success', false)->count(),
            'active_theme' => ParticleTheme::active()?->name ?? 'Yok',
            'total_themes' => ParticleTheme::count(),
            'total_settings' => Setting::count(),
            'last_login' => LoginHistory::where('success', true)->latest()->first(),
            'cache_driver' => config('cache.default'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, LoginHistory>
     */
    #[Computed]
    public function recentLogins(): \Illuminate\Database\Eloquent\Collection
    {
        return LoginHistory::with('user')
            ->latest()
            ->take(10)
            ->get();
    }

};
?>

<div>
    {{-- İstatistik Kartları --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-6 hover:border-fuchsia-500/30 transition-colors">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-fuchsia-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $this->stats['total_logins'] }}</p>
                    <p class="text-sm text-neutral-500">Başarılı Giriş</p>
                </div>
            </div>
        </div>

        <div class="bg-neutral-900 rounded-xl border border-white/5 p-6 hover:border-red-500/30 transition-colors">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-red-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $this->stats['failed_logins'] }}</p>
                    <p class="text-sm text-neutral-500">Başarısız Giriş</p>
                </div>
            </div>
        </div>

        <div class="bg-neutral-900 rounded-xl border border-white/5 p-6 hover:border-purple-500/30 transition-colors">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-lg font-bold truncate">{{ $this->stats['active_theme'] }}</p>
                    <p class="text-sm text-neutral-500">Aktif Tema</p>
                </div>
            </div>
        </div>

        <div class="bg-neutral-900 rounded-xl border border-white/5 p-6 hover:border-emerald-500/30 transition-colors">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-lg font-bold">{{ $this->stats['last_login']?->created_at->diffForHumans() ?? 'Yok' }}</p>
                    <p class="text-sm text-neutral-500">Son Giriş</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Sistem Bilgisi --}}
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Sistem Bilgisi
            </h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-white/5">
                    <span class="text-neutral-400 text-sm">Laravel</span>
                    <span class="text-sm font-mono">{{ app()->version() }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-white/5">
                    <span class="text-neutral-400 text-sm">PHP</span>
                    <span class="text-sm font-mono">{{ phpversion() }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-white/5">
                    <span class="text-neutral-400 text-sm">Cache Driver</span>
                    <span class="text-sm font-mono">{{ $this->stats['cache_driver'] }}</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-white/5">
                    <span class="text-neutral-400 text-sm">Toplam Ayar</span>
                    <span class="text-sm font-mono">{{ $this->stats['total_settings'] }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-neutral-400 text-sm">Toplam Tema</span>
                    <span class="text-sm font-mono">{{ $this->stats['total_themes'] }}</span>
                </div>
            </div>
        </div>

        {{-- Son Girişler --}}
        <div class="lg:col-span-2 bg-neutral-900 rounded-xl border border-white/5">
            <div class="p-6 border-b border-white/5 flex items-center justify-between">
                <h3 class="text-lg font-semibold">Son Girişler</h3>
                <a href="{{ route('admin.login-history') }}" class="text-sm text-fuchsia-400 hover:text-fuchsia-300 transition-colors">
                    Tümünü Gör →
                </a>
            </div>
            <div class="divide-y divide-white/5">
                @forelse($this->recentLogins as $login)
                <div class="p-4 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full {{ $login->success ? 'bg-emerald-500/10' : 'bg-red-500/10' }} flex items-center justify-center">
                        @if($login->success)
                            <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium">{{ $login->user->name ?? 'Bilinmeyen' }}</p>
                        <p class="text-sm text-neutral-500">{{ $login->ip_address }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-2 py-1 text-xs rounded {{ $login->success ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                            {{ $login->success ? 'Başarılı' : 'Başarısız' }}
                        </span>
                        <p class="text-xs text-neutral-600 mt-1">{{ $login->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-neutral-500">
                    Henüz giriş kaydı yok.
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Hızlı Eylemler --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('admin.particles') }}" class="block bg-neutral-900 rounded-xl border border-white/5 p-6 hover:border-fuchsia-500/50 transition-colors group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-fuchsia-500/10 flex items-center justify-center group-hover:bg-fuchsia-500/20 transition-colors">
                    <svg class="w-6 h-6 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold group-hover:text-fuchsia-400 transition-colors">Particles</h3>
                    <p class="text-sm text-neutral-500">Tema ve efektleri düzenle</p>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.settings') }}" class="block bg-neutral-900 rounded-xl border border-white/5 p-6 hover:border-purple-500/50 transition-colors group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-purple-500/10 flex items-center justify-center group-hover:bg-purple-500/20 transition-colors">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold group-hover:text-purple-400 transition-colors">Ayarlar</h3>
                    <p class="text-sm text-neutral-500">Site ayarlarını yönet</p>
                </div>
            </div>
        </a>

        <a href="{{ route('home') }}" target="_blank" class="block bg-neutral-900 rounded-xl border border-white/5 p-6 hover:border-cyan-500/50 transition-colors group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-cyan-500/10 flex items-center justify-center group-hover:bg-cyan-500/20 transition-colors">
                    <svg class="w-6 h-6 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold group-hover:text-cyan-400 transition-colors">Siteyi Görüntüle</h3>
                    <p class="text-sm text-neutral-500">Yeni sekmede aç</p>
                </div>
            </div>
        </a>
    </div>
</div>
