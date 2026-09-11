<?php

use App\Services\Trailer\ArtworkFetcher;

/*
  Kapak başlığı seçimi: TMDB tr-TR isteği Türkçe çeviri yoksa ORİJİNAL adı
  döndürür (Rusça olabilir → font kutu kutu basar). Sıra tr → en → dönen.
*/
function tmdbKaydi(array $ceviriler, string $title = 'Коты Эрмитажа 2'): array
{
    return [
        'title' => $title,
        'original_title' => 'Коты Эрмитажа 2',
        'original_language' => 'ru',
        'translations' => ['translations' => array_map(
            fn (array $c) => ['iso_639_1' => $c[0], 'iso_3166_1' => $c[1], 'data' => ['title' => $c[2]]],
            $ceviriler,
        )],
    ];
}

test('türkçe çeviri varsa o basılır', function () {
    $data = tmdbKaydi([['en', 'US', 'Cats in the Museum 2'], ['tr', 'TR', 'Kediler Müzede 2']], 'Kediler Müzede 2');
    expect(ArtworkFetcher::titleFrom($data))->toBe('Kediler Müzede 2');
});

test('türkçe yoksa ingilizce, orijinal rusça değil', function () {
    $data = tmdbKaydi([['ru', 'RU', ''], ['en', 'US', 'Cats in the Museum 2: Treasures of Egypt']]);
    expect(ArtworkFetcher::titleFrom($data))->toBe('Cats in the Museum 2: Treasures of Egypt');
});

test('boş türkçe çeviri atlanır', function () {
    $data = tmdbKaydi([['tr', 'TR', '   '], ['en', 'GB', 'Cats in the Museum 2']]);
    expect(ArtworkFetcher::titleFrom($data))->toBe('Cats in the Museum 2');
});

test('çeviri hiç yoksa tmdb ne döndürdüyse o', function () {
    expect(ArtworkFetcher::titleFrom(['title' => 'Bilinmeyen Film']))->toBe('Bilinmeyen Film');
    expect(ArtworkFetcher::titleFrom(['name' => 'Bir Dizi']))->toBe('Bir Dizi');
    expect(ArtworkFetcher::titleFrom([]))->toBe('İsimsiz');
});
