<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TMDB görsel kaynağı
    |--------------------------------------------------------------------------
    |
    | Kapaklar TMDB'nin görsel CDN'inden beslenir. Bu adres api.themoviedb.org
    | değil; API kesildiğinde bile görseller çoğu zaman gelmeye devam eder.
    |
    */

    'image_base' => env('TMDB_IMAGE_BASE', 'https://image.tmdb.org/t/p'),

    'sizes' => [
        'backdrop' => 'w1280',
        'poster' => 'w780',
        'logo' => 'w500',
    ],

    /*
    |--------------------------------------------------------------------------
    | Kapak üretimi
    |--------------------------------------------------------------------------
    |
    | YouTube kapağı 1280x720 ister, 2 MB üstünü reddeder. Kalite 88 bu ölçüde
    | ~300-500 KB veriyor; sınırın çok altında kalıyoruz.
    |
    */

    'thumbnail' => [
        'width' => 1280,
        'height' => 720,
        'quality' => 88,
    ],

    'fonts' => [
        'display' => public_path('fonts/Anton-Regular.ttf'),
        'heavy' => public_path('fonts/ArchivoBlack-Regular.ttf'),
        'condensed' => public_path('fonts/BebasNeue-Regular.ttf'),
        'ui' => public_path('fonts/Poppins-Bold.ttf'),
        'ui_soft' => public_path('fonts/Poppins-SemiBold.ttf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Şerit metinleri
    |--------------------------------------------------------------------------
    |
    | Kanalda iki ayrı yayın biçimi kullanılıyor; panelde bunlar arasında
    | seçim yapılır, gerekirse serbest metin yazılır.
    |
    */

    'ribbons' => [
        'altyazi' => 'Türkçe Altyazılı Fragman',
        'dublaj' => 'Türkçe Dublaj Fragman',
    ],

    'defaults' => [
        'ribbon' => env('TRAILER_RIBBON', 'Türkçe Altyazılı Fragman'),
        'tag' => env('TRAILER_TAG'),

        // null → vurgu rengi afişten otomatik çıkarılır
        'accent' => env('TRAILER_ACCENT'),
    ],

    'storage' => [
        'artwork' => storage_path('app/private/trailer/artwork'),
        'thumbnails' => storage_path('app/private/trailer/thumbnails'),
    ],

];
