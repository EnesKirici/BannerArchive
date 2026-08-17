<?php

namespace App\Services\Trailer\Templates;

use App\Services\Trailer\ThumbnailPayload;
use App\Support\Image\Canvas;

/**
 * "Shorts • Sinema" — 9:16 dikey kapak; afiş tam ekran, metin altta.
 *
 * Sinema afişleri 2:3 oranındadır, 9:16 kadraja çok az kırpmayla oturur.
 * Afiş yoksa backdrop dikeye kırpılarak kullanılır; dikeyde çok dar
 * kalacağı için hafifçe yakınlaştırılır.
 */
final class ShortsCinemaTemplate extends AbstractTemplate
{
    public function key(): string
    {
        return 'shorts_cinema';
    }

    public function label(): string
    {
        return 'Shorts • Sinema';
    }

    public function format(): string
    {
        return 'shorts';
    }

    public function supports(ThumbnailPayload $payload): bool
    {
        return $payload->hasArtwork();
    }

    public function render(ThumbnailPayload $payload): Canvas
    {
        $width = $this->width();
        $height = $this->height();
        $accent = $this->accentOf($payload);

        $canvas = Canvas::create($width, $height, '#04060c', 1.0);

        if ($payload->poster !== null) {
            $canvas->place(Canvas::open($payload->poster)->cover($width, $height, 0.30, 1.02), 0, 0);
        } elseif ($payload->backdrop !== null) {
            $canvas->place(Canvas::open($payload->backdrop)->cover($width, $height, 0.40, 1.15), 0, 0);
        }

        $canvas->overlay('#03050c', 0.16);
        $canvas->gradient(0, 0, $width, 340, '#02040a', 0.45, 0.0, 'v', 1.2);
        $canvas->gradient(0, $height - 860, $width, 860, '#02040a', 0.0, 0.96, 'v', 1.7);
        $canvas->vignette(0.42, 0.44);

        $centerX = (int) round($width / 2);

        $titleHeight = $this->titleBlock($canvas, $payload, $centerX, 1568, $width - 160, 320, 'center');

        if ($payload->meta !== null) {
            $metaBaseline = 1568 - $titleHeight - 42;
            $this->metaLine($canvas, $payload->meta, $centerX, $metaBaseline, 'center');
            $this->accentBar($canvas, $centerX, $metaBaseline - 56, $accent, 96, 'center');
        }

        $this->ribbon($canvas, $payload->ribbon, $centerX, 1608, $accent, 'center', 1.3);
        $this->brandMark($canvas, $payload);

        return $canvas;
    }
}
