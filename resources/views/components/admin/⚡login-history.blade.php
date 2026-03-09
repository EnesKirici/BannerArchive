<?php

/**
 * Livewire Login History Component
 *
 * YENİ KAVRAMLAR:
 * ---------------
 * 1. `WithPagination` trait → Livewire'da sayfalama yapar
 *    - $this->resetPage() → Filtreleme değişince ilk sayfaya döner
 *
 * 2. `wire:model.live.debounce.300ms` → Input değişikliğinden 300ms sonra günceller
 *    - Kullanıcı yazmayı bitirene kadar bekler (arama için ideal)
 *
 * 3. `updated*()` lifecycle hook → Property değiştiğinde çağrılır
 *    - updatedSearch() → $search değiştiğinde otomatik tetiklenir
 *
 * 4. `queryString` → URL'ye property'leri yazar (?search=admin&filter=success)
 *    - Sayfa yenilendiğinde filtreler korunur
 */

use App\Models\LoginHistory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('admin.layout')] #[Title('Giriş Geçmişi')] class extends Component
{
    use WithPagination;

    // ═══ URL QUERY STRING ═══
    // #[Url] → Bu property URL'de gösterilir: ?search=admin
    // Sayfa yenilendiğinde filtreler korunur!
    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all'; // all, success, failed

    #[Url]
    public int $perPage = 15;

    // ═══ UPDATED HOOK ═══
    // Property değiştiğinde pagination'ı sıfırla
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
        $this->resetPage();
    }

    /**
     * with() → Blade template'e veri gönderir (Volt'ta render() yerine kullanılır)
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $query = LoginHistory::with('user')->latest();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('ip_address', 'like', "%{$this->search}%")
                  ->orWhereHas('user', function ($uq) {
                      $uq->where('name', 'like', "%{$this->search}%")
                         ->orWhere('email', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->filter === 'success') {
            $query->where('success', true);
        } elseif ($this->filter === 'failed') {
            $query->where('success', false);
        }

        return [
            'logins' => $query->paginate($this->perPage),
            'totalCount' => LoginHistory::count(),
            'successCount' => LoginHistory::where('success', true)->count(),
            'failedCount' => LoginHistory::where('success', false)->count(),
        ];
    }
};
?>

<div>
    {{-- Üst İstatistikler --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <button wire:click="$set('filter', 'all')"
                class="p-4 rounded-xl border transition-colors text-left {{ $filter === 'all' ? 'bg-fuchsia-500/10 border-fuchsia-500/50' : 'bg-neutral-900 border-white/5 hover:border-white/10' }}">
            <p class="text-2xl font-bold">{{ $totalCount }}</p>
            <p class="text-sm text-neutral-500">Toplam Giriş</p>
        </button>
        <button wire:click="$set('filter', 'success')"
                class="p-4 rounded-xl border transition-colors text-left {{ $filter === 'success' ? 'bg-emerald-500/10 border-emerald-500/50' : 'bg-neutral-900 border-white/5 hover:border-white/10' }}">
            <p class="text-2xl font-bold text-emerald-400">{{ $successCount }}</p>
            <p class="text-sm text-neutral-500">Başarılı</p>
        </button>
        <button wire:click="$set('filter', 'failed')"
                class="p-4 rounded-xl border transition-colors text-left {{ $filter === 'failed' ? 'bg-red-500/10 border-red-500/50' : 'bg-neutral-900 border-white/5 hover:border-white/10' }}">
            <p class="text-2xl font-bold text-red-400">{{ $failedCount }}</p>
            <p class="text-sm text-neutral-500">Başarısız</p>
        </button>
    </div>

    {{-- Arama ve Filtreler --}}
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <div class="flex-1 relative">
            {{-- wire:model.live.debounce.300ms → 300ms bekleyip sonra arar --}}
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="IP adresi, kullanıcı adı veya email ile ara..."
                   class="w-full pl-10 pr-4 py-2.5 bg-neutral-900 border border-white/10 rounded-lg focus:outline-none focus:border-fuchsia-500 transition-colors text-sm">
            <svg class="absolute left-3 top-3 w-4 h-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            {{-- Arama sırasında loading göster --}}
            <div wire:loading wire:target="search" class="absolute right-3 top-3">
                <svg class="animate-spin w-4 h-4 text-fuchsia-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        </div>

        @if($search !== '' || $filter !== 'all')
        <button wire:click="clearFilters" class="px-4 py-2.5 bg-neutral-800 hover:bg-neutral-700 text-white rounded-lg transition-colors text-sm whitespace-nowrap">
            Filtreleri Temizle
        </button>
        @endif
    </div>

    {{-- Tablo --}}
    <div class="bg-neutral-900 rounded-xl border border-white/5 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-white/5">
                    <th class="text-left p-4 text-sm font-medium text-neutral-400">Kullanıcı</th>
                    <th class="text-left p-4 text-sm font-medium text-neutral-400">IP Adresi</th>
                    <th class="text-left p-4 text-sm font-medium text-neutral-400 hidden lg:table-cell">Tarayıcı</th>
                    <th class="text-left p-4 text-sm font-medium text-neutral-400">Durum</th>
                    <th class="text-left p-4 text-sm font-medium text-neutral-400">Tarih</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($logins as $login)
                <tr wire:key="login-{{ $login->id }}" class="hover:bg-white/2 transition-colors">
                    <td class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-neutral-800 flex items-center justify-center text-sm font-medium">
                                {{ substr($login->user->name ?? 'U', 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium text-sm">{{ $login->user->name ?? 'Bilinmeyen' }}</p>
                                <p class="text-xs text-neutral-500">{{ $login->user->email ?? '-' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 font-mono text-sm text-neutral-300">{{ $login->ip_address }}</td>
                    <td class="p-4 text-sm text-neutral-500 hidden lg:table-cell max-w-48 truncate">
                        {{ Str::limit($login->user_agent, 60) }}
                    </td>
                    <td class="p-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full {{ $login->success ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $login->success ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                            {{ $login->success ? 'Başarılı' : 'Başarısız' }}
                        </span>
                    </td>
                    <td class="p-4">
                        <p class="text-sm">{{ $login->created_at->format('d.m.Y H:i') }}</p>
                        <p class="text-xs text-neutral-600">{{ $login->created_at->diffForHumans() }}</p>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="p-8 text-center text-neutral-500">
                        @if($search !== '' || $filter !== 'all')
                            Filtrelere uygun kayıt bulunamadı.
                        @else
                            Henüz giriş kaydı yok.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($logins->hasPages())
    <div class="mt-6">
        {{ $logins->links() }}
    </div>
    @endif
</div>
