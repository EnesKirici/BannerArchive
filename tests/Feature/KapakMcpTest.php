<?php

declare(strict_types=1);

use App\Mcp\Servers\KapakServer;
use App\Mcp\Tools\FilmAraTool;
use App\Mcp\Tools\GorselSecenekleriTool;
use App\Mcp\Tools\KapakUretTool;
use App\Models\User;
use App\Services\Trailer\CoverGenerator;
use App\Services\Trailer\CoverSettings;
use App\Services\Trailer\ThumbnailPayload;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/** TMDB CDN'inden geliyormuş gibi davranan basit JPEG. */
function mcpSahteGorsel(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 30, 40, 110));
    imagefilledellipse($image, intdiv($width, 2), intdiv($height, 2), intdiv($width, 2), intdiv($width, 2), imagecolorallocate($image, 230, 150, 40));

    ob_start();
    imagejpeg($image, null, 85);

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->work = storage_path('framework/testing/kapak-mcp');
    config()->set('trailer.storage.artwork', $this->work.'/artwork');
    config()->set('trailer.storage.mcp', $this->work.'/mcp');

    $images = [
        'backdrops' => [
            ['file_path' => '/arka1.jpg', 'iso_639_1' => null, 'vote_average' => 6],
            ['file_path' => '/arka2.jpg', 'iso_639_1' => null, 'vote_average' => 5],
        ],
        'posters' => [['file_path' => '/afis1.jpg', 'iso_639_1' => 'tr', 'vote_average' => 5]],
        'logos' => [],
    ];

    Http::fake([
        '*/search/multi*' => Http::response(['results' => [
            ['id' => 900, 'media_type' => 'movie', 'title' => 'Fall 2', 'original_title' => 'Fall 2', 'release_date' => '2026-10-17'],
            ['id' => 901, 'media_type' => 'movie', 'title' => 'Fall', 'original_title' => 'Fall', 'release_date' => '2022-08-11'],
            ['id' => 5, 'media_type' => 'person', 'name' => 'Biri'],
        ]]),
        '*/movie/900/images*' => Http::response($images),
        '*/movie/900*' => Http::response([
            'id' => 900,
            'title' => 'Fall 2',
            'release_date' => '2026-10-17',
            'genres' => [['name' => 'Gerilim']],
            'poster_path' => '/afis1.jpg',
            'backdrop_path' => '/arka1.jpg',
            'images' => $images,
            'translations' => ['translations' => []],
        ]),
        'image.tmdb.org/*' => fn (HttpRequest $request) => Http::response(
            str_contains($request->url(), 'afis') ? mcpSahteGorsel(400, 600) : mcpSahteGorsel(640, 360),
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->work);
});

test('MCP ucu girişsiz isteği reddeder', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();
});

test('MCP ucu yönetici olmayana kapalıdır', function () {
    Passport::actingAs(User::factory()->create(['is_admin' => false]), ['mcp:use']);

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertForbidden();
});

test('OAuth istemcisini yalnızca Claude kaydedebilir', function (string $redirect, bool $allowed) {
    $response = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]]);

    $allowed ? $response->assertSuccessful()->assertJsonStructure(['client_id']) : $response->assertBadRequest();
})->with([
    'claude.ai' => ['https://claude.ai/api/mcp/auth_callback', true],
    'claude.com' => ['https://claude.com/api/mcp/auth_callback', true],
    'benzer alan adı' => ['https://claude.ai.kotu.example/geri', false],
    'yabancı site' => ['https://kotu.example/geri', false],
]);

test('yönetici MCP araçlarını listeleyebilir', function () {
    Passport::actingAs(User::factory()->create(['is_admin' => true]), ['mcp:use']);

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk()
        ->assertSee('kapak_uret')
        ->assertSee('gorsel_secenekleri')
        ->assertSee('film_ara');
});

test('film adıyla video ve Shorts kapakları üretilir', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    KapakServer::actingAs($admin)
        ->tool(KapakUretTool::class, ['film' => 'Fall 2', 'serit' => "17 Ekim'de Sinemalarda!"])
        ->assertOk()
        ->assertSee(['Fall 2', 'tmdb_id=900', '5 kapak üretildi', 'Shorts 1080×1920', 'video 1280×720', '/kapak/'.$admin->id.'/'])
        ->assertSee('Diğer eşleşmeler')
        ->assertDontSee('Biri');

    $batches = glob($this->work.'/mcp/'.$admin->id.'/*', GLOB_ONLYDIR);

    expect($batches)->toHaveCount(1)
        ->and(glob($batches[0].'/*.jpg'))->toHaveCount(5);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('yalnızca Shorts istenirse iki kapak üretilir', function () {
    KapakServer::actingAs(User::factory()->create(['is_admin' => true]))
        ->tool(KapakUretTool::class, ['tmdb_id' => 900, 'tur' => 'movie', 'bicim' => 'shorts'])
        ->assertOk()
        ->assertSee('2 kapak üretildi')
        ->assertDontSee('video 1280×720');
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('numarayla seçilen arka plan kullanılır', function () {
    KapakServer::actingAs(User::factory()->create(['is_admin' => true]))
        ->tool(KapakUretTool::class, ['tmdb_id' => 900, 'tur' => 'movie', 'bicim' => 'video', 'arka_plan' => 2])
        ->assertOk();

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/arka2.jpg'));
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('olmayan görsel numarası anlaşılır hata verir', function () {
    KapakServer::actingAs(User::factory()->create(['is_admin' => true]))
        ->tool(KapakUretTool::class, ['tmdb_id' => 900, 'tur' => 'movie', 'arka_plan' => 9])
        ->assertHasErrors(['arka_plan #9 yok; 2 seçenek var.']);
});

test('film adı ya da kimlik olmadan üretim istenmez', function () {
    KapakServer::actingAs(User::factory()->create(['is_admin' => true]))
        ->tool(KapakUretTool::class, ['serit' => 'dublaj'])
        ->assertHasErrors(['Film adı (film) ya da tmdb_id gerekli.']);
});

test('görsel seçenekleri numaralı önizlemelerle listelenir', function () {
    KapakServer::actingAs(User::factory()->create(['is_admin' => true]))
        ->tool(GorselSecenekleriTool::class, ['tmdb_id' => 900, 'tur' => 'movie', 'cesit' => 'arka_plan'])
        ->assertOk()
        ->assertSee(['2 seçenek var', 'arka_plan=<numara>', '#1 · dil: yazısız', '#2 · dil: yazısız']);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('film araması kişi sonuçlarını dışarıda bırakır', function () {
    KapakServer::actingAs(User::factory()->create(['is_admin' => true]))
        ->tool(FilmAraTool::class, ['sorgu' => 'Fall', 'yil' => 2022])
        ->assertOk()
        ->assertSee(['Fall (2022, film) · tmdb_id=901', 'Fall 2 (2026, film) · tmdb_id=900'])
        ->assertDontSee('Biri');
});

test('imzalı bağlantı kapağı girişsiz sunar, imzasızı reddeder', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $covers = app(CoverGenerator::class);

    $result = $covers->generate($admin->id, 'movie', 900, new CoverSettings(ribbon: 'Deneme'), ['video']);
    $path = array_values($result['rendered'])[0];
    $url = $covers->downloadUrl($admin->id, $result['batch'], $path);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get(strtok($url, '?'))->assertForbidden();
    $this->get(str_replace('/'.$admin->id.'/', '/'.($admin->id + 1).'/', $url))->assertForbidden();
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('şerit tercihleri payload\'a doğru uygulanır', function () {
    $payload = new ThumbnailPayload(title: 'Fall 2', poster: 'a.jpg');

    [$serbest] = (new CoverSettings(ribbon: "17 Ekim'de Sinemalarda!"))->apply($payload);
    [$yok] = (new CoverSettings(ribbon: CoverSettings::ribbonFor('yok')))->apply($payload);

    expect($serbest->ribbon)->toBe("17 Ekim'de Sinemalarda!")
        ->and($yok->ribbon)->toBe('')
        ->and(CoverSettings::ribbonFor('dublaj'))->toBe('Türkçe Dublaj Fragman')
        ->and(CoverSettings::ribbonFor('ozel', '  Yakında  '))->toBe('Yakında');
});
