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

#[Name('gorsel_yukleme_linki')]
#[Description('Kullanıcının kendi afiş/arka plan görselini yükleyeceği 30 dakika geçerli bağlantıyı verir (telefondan da açılır). Sohbete eklenen resimler bu sunucuya iletilemez; kullanıcı kendi görseliyle kapak isterse ya da TMDB\'de görsel yoksa bu bağlantıyı ver. Kullanıcı "yükledim" deyince kapak_uret\'i yuklenen_gorsel=true ile çağır.')]
#[IsReadOnly]
class GorselYuklemeLinkiTool extends Tool
{
    public function handle(Request $request, CoverGenerator $covers): Response
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $uploads = $covers->uploads($userId);

        $current = $uploads === []
            ? 'Şu an yüklü görsel yok.'
            : 'Şu an yüklü: '.collect(array_keys($uploads))->map(fn (string $kind): string => $kind === 'afis' ? 'afiş' : 'arka plan')->implode(', ').' (yeni yükleme eskisinin yerine geçer).';

        return Response::text(sprintf(
            "Yükleme bağlantısı (%d dk geçerli): %s\n%s\nAfiş dikey (Shorts ve afiş kartı), arka plan yatay (video zemini); biri yeterli.",
            CoverGenerator::UPLOAD_MINUTES,
            $covers->uploadUrl($userId),
            $current,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
