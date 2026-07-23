<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** TMDB'ye hiç ulaşılamıyormuş gibi davran. */
function tmdbDown(): void
{
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));
}

/** Verilen trend listesiyle başarılı yanıt döndür. */
function tmdbTrending(string $movieTitle, string $showName): void
{
    Http::fake([
        '*/trending/movie/day*' => Http::response(['results' => [
            ['id' => 27205, 'title' => $movieTitle, 'poster_path' => '/p.jpg', 'backdrop_path' => '/b.jpg', 'release_date' => '2010-07-16'],
        ]], 200),
        '*/trending/tv/day*' => Http::response(['results' => [
            ['id' => 1396, 'name' => $showName, 'poster_path' => '/t.jpg', 'backdrop_path' => '/tb.jpg', 'first_air_date' => '2008-01-20'],
        ]], 200),
    ]);
}

test('TMDB kapalıyken ana sayfa 500 yerine 200 döner', function () {
    tmdbDown();

    $this->get('/')->assertOk();
});

test('TMDB kapalıyken ana sayfa son başarılı listeyi (bayat kopya) gösterir', function () {
    tmdbTrending('Inception', 'Breaking Bad');
    $this->get('/')->assertOk()->assertSee('Inception');

    // Taze önbellek süresi doldu, ama bayat kopya duruyor.
    Cache::forget('tmdb_trending_movie');
    Cache::forget('tmdb_trending_tv');
    tmdbDown();

    $this->get('/')->assertOk()->assertSee('Inception');
});

test('devre kesici açılınca TMDB\'ye yeni istek gitmez', function () {
    tmdbDown();

    // Her sayfa iki uç noktayı dener; eşik 3 hata.
    $this->get('/');
    $this->get('/');

    expect(Cache::get('tmdb:circuit_open'))->toBeTrue();

    Http::fake(fn () => throw new RuntimeException('devre kesici açıkken istek yapıldı'));

    $this->get('/')->assertOk();
});

test('TMDB kapalıyken arama 500 değil 503 döner', function () {
    tmdbDown();

    $this->get('/search?query=inception')
        ->assertStatus(503)
        ->assertJsonStructure(['error']);
});

test('TMDB kapalıyken galeri 404 değil 503 döner', function () {
    tmdbDown();

    $this->get('/gallery/movie/27205')->assertStatus(503);
});

test('TMDB kapalıyken görseller uç noktası boş liste döner', function () {
    tmdbDown();

    $this->get('/images/movie/27205')
        ->assertOk()
        ->assertJson(['backdrops' => [], 'posters' => [], 'logos' => []]);
});

test('sayısal olmayan görsel yolu 500 yerine 404 döner', function () {
    $this->get('/images/stories/admin-post.php')->assertNotFound();
});
