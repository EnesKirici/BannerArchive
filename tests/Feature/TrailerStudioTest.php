<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Trailer\ThumbnailComposer;
use App\Services\Trailer\ThumbnailPayload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/** Testte TMDB'ye çıkmamak için elde üretilen basit bir afiş. */
function sahteAfis(string $path): string
{
    is_dir(dirname($path)) || mkdir(dirname($path), 0755, true);

    $image = imagecreatetruecolor(600, 900);
    imagefilledrectangle($image, 0, 0, 600, 900, imagecolorallocate($image, 20, 30, 90));
    imagefilledellipse($image, 300, 380, 320, 320, imagecolorallocate($image, 240, 140, 30));
    imagejpeg($image, $path, 85);

    return $path;
}

test('kapak stüdyosu yönetici olmayana kapalıdır', function () {
    actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin/trailers')
        ->assertForbidden();
});

test('kapak stüdyosu yöneticiye açılır', function () {
    actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin/trailers')
        ->assertSuccessful()
        ->assertSee('Kapak Stüdyosu');
});

test('önizleme ucu giriş yapmamış ziyaretçiyi içeri almaz', function () {
    get('/admin/trailers/onizleme/deneme-cinema.jpg')->assertRedirect('/login');
});

test('önizleme ucu depo dışına çıkamaz', function () {
    actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin/trailers/onizleme/'.urlencode('../../.env'))
        ->assertNotFound();
});

test('ayar tercihleri sonraki açılışta hatırlanır', function () {
    actingAs(User::factory()->create(['is_admin' => true]));

    Volt::test('admin.trailer-studio')
        ->set('brandStyle', 'beyaz')
        ->set('ribbonKey', 'dublaj')
        ->set('showMeta', false);

    // Yeni bir bileşen örneği — tercihler oturumdan geri gelmeli.
    Volt::test('admin.trailer-studio')
        ->assertSet('brandStyle', 'beyaz')
        ->assertSet('ribbonKey', 'dublaj')
        ->assertSet('showMeta', false);
});

test('şablon motoru afişten video ve shorts kapaklarını üretir', function () {
    $work = storage_path('framework/testing/trailer');
    $payload = new ThumbnailPayload(
        title: 'Çiçeğin İzi: Şafak Öncesi',
        poster: sahteAfis($work.'/afis.jpg'),
        ribbon: 'Türkçe Dublaj Fragman',
        meta: '2026 • Bilim Kurgu',
    );

    $rendered = app(ThumbnailComposer::class)->renderAll($payload, $work.'/cikti');

    // Backdrop olmasa da tüm şablonlar çalışmalı: zemine afişin bulanık hâli konur.
    expect($rendered)->toHaveKeys(['poster_card', 'cinema', 'poster_focus', 'shorts_cinema', 'shorts_poster'])
        ->and($rendered)->toHaveCount(5);

    $formats = app(ThumbnailComposer::class)->formats();

    foreach ($rendered as $key => $path) {
        [$width, $height] = getimagesize($path);

        if ($formats[$key] === 'shorts') {
            expect($width)->toBe(1080)->and($height)->toBe(1920);
        } else {
            expect($width)->toBe(1280)->and($height)->toBe(720);
        }

        expect(filesize($path))->toBeLessThan(2 * 1024 * 1024); // YouTube kapak sınırı
    }

    File::deleteDirectory($work);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('elle seçilen afiş payload üzerinde değişir', function () {
    $payload = new ThumbnailPayload(title: 'Deneme', poster: 'a.jpg');

    expect($payload->withPoster('b.jpg')->poster)->toBe('b.jpg')
        ->and($payload->withPoster(null)->poster)->toBeNull()
        ->and($payload->poster)->toBe('a.jpg');
});

test('elle yazılan film adı logoyu düşürüp metin olarak basılır', function () {
    $payload = new ThumbnailPayload(title: 'Коты Эрмитажа 2', poster: 'a.jpg', logo: 'logo.png', logoLanguage: 'ru');

    $elle = $payload->with(title: 'Kediler Müzede 2')->withoutLogo();

    expect($elle->title)->toBe('Kediler Müzede 2')
        ->and($elle->logo)->toBeNull()
        ->and($elle->titleHidden)->toBeFalse()
        ->and($payload->title)->toBe('Коты Эрмитажа 2');   // orijinal nesne değişmez
});

test('şerit ve film adı istenirse hiç basılmaz', function () {
    $work = storage_path('framework/testing/trailer-sade');
    $payload = (new ThumbnailPayload(
        title: 'Sade Kapak',
        poster: sahteAfis($work.'/afis.jpg'),
    ))->withoutRibbon()->withoutTitle()->withoutMeta();

    expect($payload->ribbon)->toBe('')
        ->and($payload->titleHidden)->toBeTrue();

    $rendered = app(ThumbnailComposer::class)->renderAll($payload, $work.'/cikti');

    expect($rendered)->toHaveCount(5);

    File::deleteDirectory($work);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('biçim süzgeci yalnızca istenen kapakları üretir', function () {
    $work = storage_path('framework/testing/trailer-bicim');
    $payload = new ThumbnailPayload(
        title: 'Gece Yarısı Ekspresi',
        poster: sahteAfis($work.'/afis.jpg'),
    );

    $composer = app(ThumbnailComposer::class);

    expect(array_keys($composer->renderAll($payload, $work.'/video', ['video'])))
        ->toBe(['poster_card', 'cinema', 'poster_focus'])
        ->and(array_keys($composer->renderAll($payload, $work.'/shorts', ['shorts'])))
        ->toBe(['shorts_cinema', 'shorts_poster']);

    File::deleteDirectory($work);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

/** Elle mod testleri: özel görseller ayrı, sonda silinen bir klasöre yazılır. */
function ozelKlasor(): string
{
    $directory = storage_path('framework/testing/trailer-ozel');
    config()->set('trailer.storage.artwork', $directory);

    return $directory;
}

test('kendi görseliyle TMDB olmadan kapak üretilir', function () {
    $directory = ozelKlasor();
    actingAs(User::factory()->create(['is_admin' => true]));

    $component = Volt::test('admin.trailer-studio')
        ->call('startManual')
        ->assertSet('manual', true)
        ->assertSet('selectedId', null)
        ->set('customTitle', 'Damat Mektebi')
        ->set('customMeta', '2026 • Komedi')
        ->set('posterUpload', UploadedFile::fake()->image('afis.jpg', 600, 900))
        ->assertHasNoErrors()
        ->assertSet('posterUpload', null);

    $stored = $component->get('customPoster');

    expect($stored)->toStartWith($directory)
        ->and(basename((string) $stored))->toStartWith('ozel-poster-')
        ->and(is_file((string) $stored))->toBeTrue();

    $component->call('chooseFormat', 'both')
        ->assertSet('error', null)
        ->assertSee('Damat Mektebi');

    expect($component->get('thumbnails'))->toHaveCount(5)
        ->and($component->get('artwork'))->toBe(['afiş' => true, 'backdrop' => false, 'logo' => false]);

    File::deleteDirectory($directory);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('elle modda görsel yüklenmeden kapak üretilmez', function () {
    ozelKlasor();
    actingAs(User::factory()->create(['is_admin' => true]));

    $component = Volt::test('admin.trailer-studio')
        ->call('startManual')
        ->set('customTitle', 'Damat Mektebi');

    expect($component->instance()->canChooseFormat())->toBeFalse();

    $component->call('chooseFormat', 'video')
        ->assertSet('thumbnails', []);

    expect($component->get('error'))->toContain('en az bir görsel');
});

test('görsel olmayan dosya afiş olarak kabul edilmez', function () {
    ozelKlasor();
    actingAs(User::factory()->create(['is_admin' => true]));

    Volt::test('admin.trailer-studio')
        ->call('startManual')
        ->set('posterUpload', UploadedFile::fake()->create('belge.pdf', 200, 'application/pdf'))
        ->assertHasErrors(['posterUpload' => 'mimes'])
        ->assertSet('customPoster', null);
});

test('yüklenen görsel kaldırılınca diskten de silinir', function () {
    $directory = ozelKlasor();
    actingAs(User::factory()->create(['is_admin' => true]));

    $component = Volt::test('admin.trailer-studio')
        ->call('startManual')
        ->set('backdropUpload', UploadedFile::fake()->image('zemin.png', 1600, 900));

    $stored = (string) $component->get('customBackdrop');
    expect(is_file($stored))->toBeTrue();

    $component->call('removeCustom', 'backdrop')
        ->assertSet('customBackdrop', null);

    expect(is_file($stored))->toBeFalse();

    File::deleteDirectory($directory);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');

test('önizleme ucu kullanıcının kendi yüklediği görseli sunar, başkasınınkini sunmaz', function () {
    $directory = ozelKlasor();
    $owner = User::factory()->create(['is_admin' => true]);
    $other = User::factory()->create(['is_admin' => true]);

    sahteAfis($directory.'/ozel/'.$owner->id.'/ozel-poster-abcdef123456.jpg');

    actingAs($owner)
        ->get('/admin/trailers/onizleme/ozel-poster-abcdef123456.jpg')
        ->assertSuccessful();

    actingAs($other)
        ->get('/admin/trailers/onizleme/ozel-poster-abcdef123456.jpg')
        ->assertNotFound();

    File::deleteDirectory($directory);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');
