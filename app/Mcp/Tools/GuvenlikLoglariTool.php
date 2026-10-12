<?php

namespace App\Mcp\Tools;

use App\Models\SecurityLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('guvenlik_loglari')]
#[Description('Güvenlik olaylarını listeler: otomatik banlar, rate limit uyarıları, şüpheli yüklemeler vb. (en yeni önce). IP, açıklama ya da tarayıcıda arama ve olay türüne göre süzme yapılabilir.')]
#[IsReadOnly]
class GuvenlikLoglariTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'arama' => ['nullable', 'string', 'max:100'],
            'olay_turu' => ['nullable', 'string', 'max:50'],
            'adet' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SecurityLog::query()->latest();
        $search = trim((string) ($validated['arama'] ?? ''));

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('ip_address', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('user_agent', 'like', $like));
        }

        if (($validated['olay_turu'] ?? '') !== '') {
            $query->where('event_type', $validated['olay_turu']);
        }

        $logs = $query->take((int) ($validated['adet'] ?? 30))->get();
        $today = now()->startOfDay();

        $header = sprintf(
            "Toplam %d kayıt, bugün %d (tekil IP %d). Olay türleri: %s\n",
            SecurityLog::count(),
            SecurityLog::where('created_at', '>=', $today)->count(),
            SecurityLog::where('created_at', '>=', $today)->distinct('ip_address')->count('ip_address'),
            SecurityLog::select('event_type')->distinct()->orderBy('event_type')->pluck('event_type')->implode(', '),
        );

        if ($logs->isEmpty()) {
            return Response::text($header.'Eşleşen kayıt yok.');
        }

        return Response::text($header.$logs->map(fn (SecurityLog $log): string => sprintf(
            '- %s · %s · %s · %s%s%s',
            $log->created_at?->format('d.m.Y H:i'),
            $log->event_type,
            $log->ip_address,
            $log->description,
            $log->url ? ' · '.$log->url : '',
            $log->user_agent ? ' · '.mb_strimwidth((string) $log->user_agent, 0, 50, '…') : '',
        ))->implode("\n"));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'arama' => $schema->string()->description('IP, açıklama ya da tarayıcı bilgisinde geçen metin.'),
            'olay_turu' => $schema->string()->description('Olay türü (örn. ban_rate_limit, rate_warning, suspicious_upload). Boş = hepsi.'),
            'adet' => $schema->integer()->description('En fazla 100.')->default(30),
        ];
    }
}
