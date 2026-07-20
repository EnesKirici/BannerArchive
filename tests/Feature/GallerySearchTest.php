<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('gallery search lists movie and tv results without persons', function () {
    Http::fake([
        '*/search/multi*' => Http::response(['results' => [
            ['media_type' => 'movie', 'id' => 27205, 'title' => 'Inception', 'poster_path' => '/p.jpg', 'release_date' => '2010-07-16'],
            ['media_type' => 'person', 'id' => 500, 'name' => 'Bir Oyuncu'],
            ['media_type' => 'tv', 'id' => 1396, 'name' => 'Breaking Bad', 'poster_path' => '/bb.jpg', 'first_air_date' => '2008-01-20'],
        ]], 200),
    ]);

    $component = Volt::test('gallery-search')
        ->set('search', 'incep')
        ->assertSee('Inception')
        ->assertSee('Breaking Bad');

    expect($component->get('results'))->toHaveCount(2);
});

test('gallery search clears results for short queries', function () {
    $component = Volt::test('gallery-search')
        ->set('results', [['id' => 1, 'title' => 'X', 'poster_path' => '', 'release_date' => null, 'type' => 'Film', 'raw_type' => 'movie']])
        ->set('search', 'a');

    expect($component->get('results'))->toBeEmpty();
});

test('gallery search navigates to the selected gallery', function () {
    Volt::test('gallery-search')
        ->call('goToGallery', 'movie', 27205)
        ->assertRedirect(route('gallery', ['type' => 'movie', 'id' => 27205]));
});

test('gallery search ignores invalid media types', function () {
    Volt::test('gallery-search')
        ->call('goToGallery', 'person', 500)
        ->assertNoRedirect();
});
