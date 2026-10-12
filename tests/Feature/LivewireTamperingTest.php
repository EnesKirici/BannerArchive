<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Http::fake([
        '*' => Http::response(['results' => [
            ['id' => 27205, 'title' => 'Inception', 'name' => 'Inception', 'media_type' => 'movie', 'poster_path' => '/p.jpg', 'backdrop_path' => '/b.jpg', 'release_date' => '2010-07-16'],
        ]], 200),
    ]);
});

test('istemci sunucuda doldurulan listeleri değiştiremez', function (string $component, string $property, mixed $value) {
    expect(fn () => Livewire::test($component)->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'keşfet listesi' => ['category-browser', 'items', [1, 2, 3]],
    'keşfet tür' => ['category-browser', 'mediaType', 'movie/../../account'],
    'keşfet kategori' => ['category-browser', 'category', '../account'],
    'arama sonuçları' => ['movie-search', 'results', [1]],
    'arama süzgeci' => ['movie-search', 'filter', 'x'],
    'arama hata metni' => ['movie-search', 'tmdbError', '<b>sahte</b>'],
]);

test('keşfet izin listesi dışındaki tür ve kategoriyi yok sayar', function () {
    Livewire::test('category-browser')
        ->call('setMediaType', 'person')
        ->assertSet('mediaType', 'movie')
        ->call('setCategory', '../account')
        ->assertSet('category', 'popular')
        ->call('setCategory', 'top_rated')
        ->assertSet('category', 'top_rated')
        ->assertSee('Inception');
});

test('arama süzgeci yalnızca bilinen değerleri kabul eder', function () {
    Livewire::test('movie-search')
        ->call('setFilter', 'person')
        ->assertSet('filter', 'all')
        ->call('setFilter', 'tv')
        ->assertSet('filter', 'tv');
});

test('kenar çubuğundan gelen bozuk yük görünümü patlatmaz', function (array $movie) {
    Livewire::test('movie-search')
        ->dispatch('select-from-sidebar', movie: $movie)
        ->assertOk()
        ->assertSet('results', []);
})->with([
    'boş' => [[]],
    'kimliksiz' => [['title' => 'Inception']],
    'başlık dizi' => [['id' => 5, 'title' => ['x']]],
    'kimlik metin' => [['id' => 'abc', 'title' => 'Inception']],
]);

test('kenar çubuğundan gelen geçerli yük temizlenerek listelenir', function () {
    Livewire::test('movie-search')
        ->dispatch('select-from-sidebar', movie: [
            'id' => '27205',
            'title' => 'Inception',
            'overview' => ['beklenmeyen'],
            'backdrop_path' => '/b.jpg',
            'vote_average' => '8.4',
            'raw_type' => 'bilinmeyen',
            'fazladan' => 'alan',
        ])
        ->assertOk()
        ->assertSet('search', 'Inception')
        ->assertSet('filter', 'movie')
        ->assertSet('results', [[
            'id' => 27205,
            'title' => 'Inception',
            'overview' => '',
            'poster_path' => '',
            'backdrop_path' => '/b.jpg',
            'vote_average' => 8.4,
            'release_date' => null,
            'type' => 'Film',
            'raw_type' => 'movie',
        ]]);
});
