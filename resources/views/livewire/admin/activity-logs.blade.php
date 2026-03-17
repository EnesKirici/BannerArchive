<?php

use App\Models\ActivityLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('admin.layout')] #[Title('Aktivite Logları')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $action = 'all';

    #[Url]
    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = min(max($this->perPage, 10), 100);
        $this->resetPage();
    }

    public function clearOldLogs(): void
    {
        ActivityLog::where('created_at', '<', now()->subDays(30))->delete();
    }

    #[Computed]
    public function stats(): array
    {
        $today = now()->startOfDay();

        return [
            'total_today' => ActivityLog::where('created_at', '>=', $today)->count(),
            'searches_today' => ActivityLog::where('action', 'search')->where('created_at', '>=', $today)->count(),
            'downloads_today' => ActivityLog::where('action', 'download')->where('created_at', '>=', $today)->count(),
            'unique_ips_today' => ActivityLog::where('created_at', '>=', $today)->distinct('ip_address')->count('ip_address'),
        ];
    }

    public function with(): array
    {
        $query = ActivityLog::with('user')->latest();

        if ($this->search) {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $this->search);
            $query->where(function ($q) use ($escaped) {
                $q->where('description', 'like', "%{$escaped}%")
                    ->orWhere('ip_address', 'like', "%{$escaped}%");
            });
        }

        if ($this->action !== 'all') {
            $query->where('action', $this->action);
        }

        return [
            'logs' => $query->paginate($this->perPage),
        ];
    }
};
?>

<div>
    {{-- İstatistikler --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-4">
            <p class="text-2xl font-bold">{{ $this->stats['total_today'] }}</p>
            <p class="text-xs text-neutral-500">Bugün Toplam</p>
        </div>
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-4">
            <p class="text-2xl font-bold text-fuchsia-400">{{ $this->stats['searches_today'] }}</p>
            <p class="text-xs text-neutral-500">Arama</p>
        </div>
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-4">
            <p class="text-2xl font-bold text-purple-400">{{ $this->stats['downloads_today'] }}</p>
            <p class="text-xs text-neutral-500">İndirme</p>
        </div>
        <div class="bg-neutral-900 rounded-xl border border-white/5 p-4">
            <p class="text-2xl font-bold text-cyan-400">{{ $this->stats['unique_ips_today'] }}</p>
            <p class="text-xs text-neutral-500">Tekil Ziyaretçi</p>
        </div>
    </div>

    {{-- Filtreler --}}
    <div class="flex flex-col md:flex-row gap-3 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="IP veya içerik ara..."
            class="w-full md:w-80 bg-neutral-800 border border-white/10 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-fuchsia-500/50 transition-colors">

        <div class="flex gap-2">
            @foreach([
                'all' => 'Tümü',
                'search' => 'Arama',
                'gallery' => 'Galeri',
                'download' => 'İndirme',
                'quote' => 'Quote',
            ] as $value => $label)
                <button wire:click="$set('action', '{{ $value }}')"
                    class="px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ $action === $value ? 'bg-fuchsia-600 text-white' : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <button wire:click="clearOldLogs" wire:confirm="30 günden eski loglar silinecek. Emin misiniz?"
            class="ml-auto px-4 py-2 rounded-lg text-xs font-medium bg-neutral-800 text-neutral-400 hover:bg-red-600/20 hover:text-red-400 transition-colors">
            Eski Logları Temizle
        </button>
    </div>

    {{-- Tablo --}}
    <div class="bg-neutral-900 rounded-xl border border-white/5 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-white/5">
                    <th class="text-left text-xs uppercase tracking-wider text-neutral-500 font-medium px-6 py-4">Tarih</th>
                    <th class="text-left text-xs uppercase tracking-wider text-neutral-500 font-medium px-6 py-4">IP</th>
                    <th class="text-left text-xs uppercase tracking-wider text-neutral-500 font-medium px-6 py-4">Kullanıcı</th>
                    <th class="text-left text-xs uppercase tracking-wider text-neutral-500 font-medium px-6 py-4">İşlem</th>
                    <th class="text-left text-xs uppercase tracking-wider text-neutral-500 font-medium px-6 py-4">Detay</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($logs as $log)
                <tr class="hover:bg-white/[0.02] transition-colors" wire:key="log-{{ $log->id }}">
                    <td class="px-6 py-3">
                        <p class="text-sm">{{ $log->created_at->format('d.m.Y H:i') }}</p>
                        <p class="text-xs text-neutral-600">{{ $log->created_at->diffForHumans() }}</p>
                    </td>
                    <td class="px-6 py-3">
                        <span class="font-mono text-xs text-neutral-400">{{ $log->ip_address }}</span>
                    </td>
                    <td class="px-6 py-3">
                        @if($log->user)
                            <span class="text-sm text-fuchsia-400">{{ $log->user->name }}</span>
                        @else
                            <span class="text-xs text-neutral-600">Misafir</span>
                        @endif
                    </td>
                    <td class="px-6 py-3">
                        @php
                            $actionStyles = [
                                'search' => 'bg-fuchsia-500/10 text-fuchsia-400',
                                'gallery' => 'bg-purple-500/10 text-purple-400',
                                'download' => 'bg-cyan-500/10 text-cyan-400',
                                'quote' => 'bg-yellow-500/10 text-yellow-400',
                            ];
                            $actionLabels = [
                                'search' => 'Arama',
                                'gallery' => 'Galeri',
                                'download' => 'İndirme',
                                'quote' => 'Quote',
                            ];
                        @endphp
                        <span class="text-xs px-2 py-1 rounded {{ $actionStyles[$log->action] ?? 'bg-neutral-500/10 text-neutral-400' }}">
                            {{ $actionLabels[$log->action] ?? $log->action }}
                        </span>
                    </td>
                    <td class="px-6 py-3">
                        <p class="text-sm text-neutral-300 truncate max-w-xs" title="{{ $log->description }}">{{ $log->description }}</p>
                        @if($log->metadata)
                            <p class="text-xs text-neutral-600">{{ collect($log->metadata)->map(fn($v, $k) => "{$k}: {$v}")->implode(', ') }}</p>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-neutral-500">Henüz aktivite kaydı yok.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    @endif
</div>
