<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\FilmAraTool;
use App\Mcp\Tools\GorselSecenekleriTool;
use App\Mcp\Tools\KapakUretTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('BannerArchive Kapak Stüdyosu')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
YouTube fragman kapakları (video 16:9 ve Shorts 9:16) üretir. Kullanıcı HIZ istiyor: soru sorma, doğrudan üret.

Akış:
1. `kapak_uret`'i film adıyla çağır. Kullanıcının söylediği şerit metnini `serit`'e aynen yaz (örn. "17 Ekim'de Sinemalarda!"). Biçim belirtilmediyse ikisini de üret.
2. Dönen önizlemelere bak. Her biçimden EN İYİ kapağı kendin seç: yüz/karakter net ve kesilmemiş, logo okunur, şerit görseli kapatmıyor, arka planda yazı yok.
3. Belirgin bir kusur varsa (yüz logonun ya da şeridin altında, logo okunmuyor, görsel bulanık, afişte film adı zaten yazılıyken üstüne logo da basılmış) kullanıcıya sormadan `gorsel_secenekleri` ile başka arka plan/afiş/logo seç ve `kapak_uret`'i o numarayla tekrar çağır. En fazla 2 deneme.
4. Kısa yanıt ver: seçtiğin video ve Shorts kapağının indirme bağlantısı + tek cümle neden. Diğer kapakların bağlantılarını alt satırda listele.

Film yanlış eşleştiyse (yanıttaki "diğer eşleşmeler"), `tmdb_id` ile doğrusunu üret. Kullanıcı "beğenmedim", "başka arka plan", "logo değişsin" derse `gorsel_secenekleri`'ne bakıp yeni numarayla üret.
MD)]
class KapakServer extends Server
{
    protected array $tools = [
        KapakUretTool::class,
        GorselSecenekleriTool::class,
        FilmAraTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
