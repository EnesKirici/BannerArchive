<?php

namespace App\Mcp\Tools;

use App\Models\LoginHistory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('giris_gecmisi')]
#[Description('Panel giriş denemelerini listeler (en yeni önce): tarih, sonuç, kullanıcı, IP, tarayıcı. IP ya da kullanıcı adı/e-postayla aranabilir.')]
#[IsReadOnly]
class GirisGecmisiTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'arama' => ['nullable', 'string', 'max:100'],
            'durum' => ['nullable', 'in:hepsi,basarili,basarisiz'],
            'adet' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) ($validated['arama'] ?? ''));
        $query = LoginHistory::with('user')->latest();

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('ip_address', 'like', $like)
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        }

        match ($validated['durum'] ?? 'hepsi') {
            'basarili' => $query->where('success', true),
            'basarisiz' => $query->where('success', false),
            default => null,
        };

        $logins = $query->take((int) ($validated['adet'] ?? 20))->get();

        if ($logins->isEmpty()) {
            return Response::text('Kayıt yok.');
        }

        return Response::text(sprintf("Toplam %d giriş (%d başarılı, %d başarısız). Eşleşenlerden %d tanesi:\n", LoginHistory::count(), LoginHistory::where('success', true)->count(), LoginHistory::where('success', false)->count(), $logins->count())
            .$logins->map(fn (LoginHistory $login): string => sprintf(
                '- %s · %s · %s · %s · %s',
                $login->created_at?->format('d.m.Y H:i'),
                $login->success ? 'başarılı' : 'BAŞARISIZ',
                $login->user?->email ?? 'bilinmeyen',
                $login->ip_address,
                mb_strimwidth((string) $login->user_agent, 0, 60, '…'),
            ))->implode("\n"));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'arama' => $schema->string()->description('IP, kullanıcı adı ya da e-postada geçen metin.'),
            'durum' => $schema->string()->enum(['hepsi', 'basarili', 'basarisiz'])->default('hepsi'),
            'adet' => $schema->integer()->description('En fazla 100.')->default(20),
        ];
    }
}
