<?php

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

test('the application returns clickable popular sidebar items', function () {
    Http::fake([
        'https://api.themoviedb.org/3/trending/movie/day*' => Http::response([
            'results' => [[
                'id' => 1,
                'title' => 'Inception',
                'overview' => 'Bir rüya içinde rüya hikayesi.',
                'poster_path' => '/poster.jpg',
                'backdrop_path' => '/backdrop.jpg',
                'vote_average' => 8.8,
                'release_date' => '2010-07-16',
            ]],
        ], 200),
        'https://api.themoviedb.org/3/trending/tv/day*' => Http::response([
            'results' => [[
                'id' => 2,
                'name' => 'Dark',
                'overview' => 'Zamanda yolculuk gizemi.',
                'poster_path' => '/dark-poster.jpg',
                'backdrop_path' => '/dark-backdrop.jpg',
                'vote_average' => 8.7,
                'first_air_date' => '2017-12-01',
            ]],
        ], 200),
        '*' => Http::response(['results' => []], 200),
    ]);

    $response = get('/');

    $response->assertSuccessful();
    $response->assertSee('data-sidebar-item', false);
    $response->assertSee('data-tmdb-raw-type="movie"', false);
    $response->assertSee('data-tmdb-raw-type="tv"', false);
});
