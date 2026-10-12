<?php

namespace App\Mcp\Tools;

use App\Models\ActivityLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('aktivite_loglari')]
#[Description('Ziyaretçi aktivitesini listeler: aramalar, banner indirmeleri, video indirmeleri vb. (en yeni önce). Açıklama ya da IP ile aranabilir, işlem türüne göre süzülebilir. "En çok ne aranıyor" gibi sorular için uygun.')]
#[IsReadOnly]
class AktiviteLoglariTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'arama' => ['nullable', 'string', 'max:100'],
            'islem' => ['nullable', 'string', 'max:50'],
            'adet' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ActivityLog::with('user')->latest();
        $search = trim((string) ($validated['arama'] ?? ''));

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('description', 'like', $like)->orWhere('ip_address', 'like', $like));
        }

        if (($validated['islem'] ?? 'all') !== 'all' && ($validated['islem'] ?? '') !== '') {
            $query->where('action', $validated['islem']);
        }

        $logs = $query->take((int) ($validated['adet'] ?? 30))->get();
        $actions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action')->implode(', ');

        if ($logs->isEmpty()) {
            return Response::text("Kayıt yok. Mevcut işlem türleri: {$actions}");
        }

        return Response::text("İşlem türleri: {$actions}\n"
            .$logs->map(fn (ActivityLog $log): string => sprintf(
                '- %s · %s · %s · %s%s',
                $log->created_at?->format('d.m.Y H:i'),
                $log->action,
                $log->description,
                $log->ip_address,
                $log->user ? ' · '.$log->user->email : '',
            ))->implode("\n"));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'arama' => $schema->string()->description('Açıklamada ya da IP\'de geçen metin (örn. film adı).'),
            'islem' => $schema->string()->description('İşlem türü (search, download, video_download…). Boş = hepsi; yanıtta mevcut türler listelenir.'),
            'adet' => $schema->integer()->description('En fazla 100.')->default(30),
        ];
    }
}
