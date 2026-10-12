<?php

namespace App\Mcp\Tools;

use App\Models\BlockedIp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('engelli_ipler')]
#[Description('Engellenmiş IP\'leri listeler: IP, sebep, ban türü, ihlal sayısı, bitiş zamanı (kalıcı/geçici). IP ya da sebepte arama, ban türüne göre süzme yapılabilir.')]
#[IsReadOnly]
class EngelliIplerTool extends Tool
{
    private const BAN_TYPES = ['manual', 'rate_limit', 'suspicious_upload', 'brute_force'];

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'arama' => ['nullable', 'string', 'max:100'],
            'ban_turu' => ['nullable', 'in:'.implode(',', self::BAN_TYPES)],
            'adet' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Süresi dolmuş geçici banlar artık etkisiz; listelenmez (silmek panelin işi).
        $active = fn () => BlockedIp::query()->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()));

        $query = $active()->latest();
        $search = trim((string) ($validated['arama'] ?? ''));

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('ip_address', 'like', $like)->orWhere('reason', 'like', $like));
        }

        if (isset($validated['ban_turu'])) {
            $query->where('ban_type', $validated['ban_turu']);
        }

        $header = sprintf(
            "Etkin %d engel: %d kalıcı, %d geçici · türler: %s\n",
            $active()->count(),
            $active()->whereNull('blocked_until')->count(),
            $active()->whereNotNull('blocked_until')->count(),
            collect(self::BAN_TYPES)->map(fn (string $type): string => $type.' '.$active()->where('ban_type', $type)->count())->implode(', '),
        );

        $blocked = $query->take((int) ($validated['adet'] ?? 30))->get();

        if ($blocked->isEmpty()) {
            return Response::text($header.'Eşleşen kayıt yok.');
        }

        return Response::text($header.$blocked->map(fn (BlockedIp $ip): string => sprintf(
            '- %s · %s · %s · %d ihlal · %s · engellendi %s',
            $ip->ip_address,
            $ip->ban_type,
            $ip->reason,
            (int) $ip->violation_count,
            $ip->blocked_until ? 'bitiş '.$ip->blocked_until->format('d.m.Y H:i') : 'KALICI',
            $ip->created_at?->format('d.m.Y H:i'),
        ))->implode("\n"));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'arama' => $schema->string()->description('IP ya da sebepte geçen metin.'),
            'ban_turu' => $schema->string()->enum(self::BAN_TYPES),
            'adet' => $schema->integer()->description('En fazla 100.')->default(30),
        ];
    }
}
