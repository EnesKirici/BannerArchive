<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AktiviteLoglariTool;
use App\Mcp\Tools\EngelliIplerTool;
use App\Mcp\Tools\FilmAraTool;
use App\Mcp\Tools\GirisGecmisiTool;
use App\Mcp\Tools\GorselSecenekleriTool;
use App\Mcp\Tools\GuvenlikLoglariTool;
use App\Mcp\Tools\KapakUretTool;
use App\Mcp\Tools\PanelOzetiTool;
use App\Mcp\Tools\ParticleTemalariTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('BannerArchive Yönetim Paneli')]
#[Version('1.1.0')]
#[Instructions(<<<'MD'
BannerArchive (bannerarchive.elw.com.tr) admin panelinin araçları. Kullanıcı sitenin sahibi ve HIZ istiyor.

## Fragman kapakları
Kapak üretiminde soru sorma, doğrudan üret.
1. `kapak_uret`'i film adıyla çağır. Kullanıcının söylediği şerit metnini `serit`'e aynen yaz (örn. "17 Ekim'de Sinemalarda!"). Biçim belirtilmediyse ikisini de üret.
2. Dönen önizlemelere bak. Her biçimden EN İYİ kapağı kendin seç: yüz/karakter net ve kesilmemiş, logo okunur, şerit görseli kapatmıyor, arka planda yazı yok.
3. Belirgin bir kusur varsa (yüz logonun ya da şeridin altında, logo okunmuyor, görsel bulanık, afişte film adı zaten yazılıyken üstüne logo da basılmış) kullanıcıya sormadan `gorsel_secenekleri` ile başka arka plan/afiş/logo seç ve `kapak_uret`'i o numarayla tekrar çağır. En fazla 2 deneme.
4. Kısa yanıt ver: seçtiğin video ve Shorts kapağının indirme bağlantısı + tek cümle neden. Diğer kapakların bağlantılarını alt satırda listele.

Film yanlış eşleştiyse (yanıttaki "diğer eşleşmeler"), `tmdb_id` ile doğrusunu üret. Kullanıcı "beğenmedim", "başka arka plan", "logo değişsin" derse `gorsel_secenekleri`'ne bakıp yeni numarayla üret.

## Site durumu (yalnızca okuma)
- Genel durum sorularında önce `panel_ozeti`; ayrıntı için `giris_gecmisi`, `aktivite_loglari`, `guvenlik_loglari`, `engelli_ipler`, `particle_temalari`.
- Logları özetlerken ham listeyi kopyalama: önemli olanı söyle (örn. "dün 3 IP rate limit'ten banlandı, hepsi bot gibi görünüyor").
- Bu araçlar hiçbir şeyi değiştirmez. Ayar değiştirme, IP engeli, log/cache temizliği gibi işlemler için kullanıcıyı admin paneline yönlendir.
MD)]
class KapakServer extends Server
{
    protected array $tools = [
        KapakUretTool::class,
        GorselSecenekleriTool::class,
        FilmAraTool::class,
        PanelOzetiTool::class,
        GirisGecmisiTool::class,
        AktiviteLoglariTool::class,
        GuvenlikLoglariTool::class,
        EngelliIplerTool::class,
        ParticleTemalariTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
