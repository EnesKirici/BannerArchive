<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AVIF Kodek Ayarları
    |--------------------------------------------------------------------------
    |
    | Bazı dağıtımlarda sistem libgd kütüphanesi libavif olmadan derlenir.
    | Böyle bir sunucuda gd_info() "AVIF Support => 1" dese bile
    | imagecreatefromavif() çalışmaz, imageavif() ise 0 byte dosya üretir.
    | Bu durumda AvifCodec, libavif-bin araçlarına (avifdec/avifenc) düşer.
    |
    */

    'avif' => [
        // Harici araç yolları (libavif-bin paketi)
        'decoder' => env('AVIF_DECODER', 'avifdec'),
        'encoder' => env('AVIF_ENCODER', 'avifenc'),

        // Harici araç çalışma süresi limiti (saniye)
        'timeout' => (int) env('AVIF_TIMEOUT', 30),

        // avifenc hız/kalite dengesi (0 en yavaş, 10 en hızlı)
        'speed' => (int) env('AVIF_SPEED', 6),

        // Yetenek tespitinin önbellekte tutulma süresi (saniye)
        'probe_ttl' => (int) env('AVIF_PROBE_TTL', 3600),
    ],

];
