<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Trailer\ThumbnailComposer;
use App\Services\Trailer\ThumbnailPayload;
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

test('şablon motoru afişten 1280x720 kapak üretir', function () {
    $work = storage_path('framework/testing/trailer');
    $payload = new ThumbnailPayload(
        title: 'Çiçeğin İzi: Şafak Öncesi',
        poster: sahteAfis($work.'/afis.jpg'),
        ribbon: 'Türkçe Dublaj Fragman',
        meta: '2026 • Bilim Kurgu',
    );

    $rendered = app(ThumbnailComposer::class)->renderAll($payload, $work.'/cikti');

    // Backdrop olmasa da üç şablon da çalışmalı: zemine afişin bulanık hâli konur.
    expect($rendered)->toHaveKeys(['poster_card', 'cinema', 'poster_focus'])
        ->and($rendered)->toHaveCount(3);

    foreach ($rendered as $path) {
        [$width, $height] = getimagesize($path);

        expect($width)->toBe(1280)
            ->and($height)->toBe(720)
            ->and(filesize($path))->toBeLessThan(2 * 1024 * 1024); // YouTube kapak sınırı
    }

    File::deleteDirectory($work);
})->skip(! extension_loaded('gd'), 'GD eklentisi yok');
