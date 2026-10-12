<?php

namespace App\Mcp\Tools;

use App\Services\Trailer\CoverGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('gorsel_secenekleri')]
#[Description('Bir filmin TMDB\'deki arka plan, afiş ya da logo seçeneklerini numaralı küçük önizlemelerle gösterir. Beğenilmeyen görseli değiştirmek için: buradan numara seç, kapak_uret\'e arka_plan / afis / logo_no olarak ver.')]
#[IsReadOnly]
class GorselSecenekleriTool extends Tool
{
    private const PER_PAGE = 10;

    public function handle(Request $request, CoverGenerator $covers): Response|ResponseFactory
    {
        $validated = $request->validate([
            'tmdb_id' => ['required', 'integer', 'min:1'],
            'tur' => ['required', 'in:movie,tv'],
            'cesit' => ['required', 'in:arka_plan,afis,logo'],
            'sayfa' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $options = $covers->options($validated['tur'], (int) $validated['tmdb_id'], $validated['cesit']);
        $page = (int) ($validated['sayfa'] ?? 1);
        $offset = ($page - 1) * self::PER_PAGE;
        $slice = array_slice($options, $offset, self::PER_PAGE, true);

        if ($slice === []) {
            return Response::text($options === [] ? 'TMDB\'de bu türde görsel yok.' : 'Bu sayfada görsel yok.');
        }

        $parameter = match ($validated['cesit']) {
            'afis' => 'afis',
            'logo' => 'logo_no',
            default => 'arka_plan',
        };

        $responses = [Response::text(sprintf(
            '%d seçenek var, %d–%d gösteriliyor. kapak_uret\'e %s=<numara> ver.%s',
            count($options),
            $offset + 1,
            $offset + count($slice),
            $parameter,
            $offset + count($slice) < count($options) ? ' Devamı için sayfa='.($page + 1).'.' : '',
        ))];

        foreach ($slice as $index => $option) {
            $preview = $covers->optionPreview($option['path'], $validated['cesit']);

            if ($preview === null) {
                continue;
            }

            $responses[] = Response::text(sprintf('#%d · dil: %s', $index + 1, $option['dil'] ?? 'yazısız'));
            $responses[] = Response::image($preview, 'image/jpeg');
        }

        return Response::make($responses);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tmdb_id' => $schema->integer()->description('kapak_uret yanıtındaki TMDB kimliği.')->required(),
            'tur' => $schema->string()->enum(['movie', 'tv'])->description('movie = film, tv = dizi.')->required(),
            'cesit' => $schema->string()->enum(['arka_plan', 'afis', 'logo'])
                ->description('arka_plan = video kapaklarının zemini · afis = Shorts zemini ve afiş kartı · logo = film adı logosu.')
                ->required(),
            'sayfa' => $schema->integer()->description('Sayfa başına 10 görsel.')->default(1),
        ];
    }
}
