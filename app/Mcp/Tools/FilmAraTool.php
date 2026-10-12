<?php

namespace App\Mcp\Tools;

use App\Services\Trailer\CoverGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Name('film_ara')]
#[Description('TMDB\'de film/dizi arar; TMDB kimliği, türü ve yılıyla listeler. Yalnızca kapak_uret yanlış filmi seçtiğinde doğru tmdb_id\'yi bulmak için gerekir.')]
#[IsReadOnly]
class FilmAraTool extends Tool
{
    public function handle(Request $request, CoverGenerator $covers): Response
    {
        $validated = $request->validate([
            'sorgu' => ['required', 'string', 'min:2', 'max:120'],
            'yil' => ['nullable', 'integer', 'between:1900,2100'],
        ]);

        try {
            $results = $covers->search($validated['sorgu'], $validated['yil'] ?? null);
        } catch (RuntimeException $exception) {
            return Response::error($exception->getMessage());
        }

        if ($results === []) {
            return Response::text('Sonuç bulunamadı.');
        }

        return Response::text(collect($results)
            ->map(fn (array $item): string => sprintf(
                '- %s (%s, %s) · tmdb_id=%d, tur=%s%s',
                $item['title'],
                $item['year'] ?: 'yıl yok',
                $item['type'] === 'tv' ? 'dizi' : 'film',
                $item['id'],
                $item['type'],
                $item['original'] !== '' && $item['original'] !== $item['title'] ? ' · orijinal: '.$item['original'] : '',
            ))
            ->implode("\n"));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sorgu' => $schema->string()->description('Film ya da dizi adı.')->required(),
            'yil' => $schema->integer()->description('Biliniyorsa çıkış yılı; o yıldakiler başa alınır.'),
        ];
    }
}
