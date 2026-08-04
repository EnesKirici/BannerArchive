<?php

namespace App\Services\Trailer\Templates;

use App\Services\Trailer\ThumbnailPayload;
use App\Support\Image\Canvas;

/**
 * "Sinema" — tam ekran backdrop, ortada logo ve şerit.
 *
 * Afişi hiç göstermez; sahnenin kendisi öne çıkar. Güçlü bir backdrop ya da
 * logo varsa en temiz duran şablon budur.
 */
final class CinemaTemplate extends AbstractTemplate
{
    public function key(): string
    {
        return 'cinema';
    }

    public function label(): string
    {
        return 'Sinema';
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

        $canvas = $this->background($payload, 0.38);
        $canvas->overlay('#03050c', 0.30);
        $canvas->gradient(0, 0, $width, 240, '#02040a', 0.55, 0.0, 'v', 1.2);
        $canvas->gradient(0, $height - 400, $width, 400, '#02040a', 0.0, 0.90, 'v', 1.6);
        $canvas->vignette(0.58, 0.34);

        $centerX = (int) round($width / 2);

        $titleHeight = $this->titleBlock($canvas, $payload, $centerX, 452, 960, 210, 'center');

        $metaBaseline = 452 - $titleHeight - 36;
        $this->metaLine($canvas, $payload->meta, $centerX, $metaBaseline, 'center');
        $this->accentBar($canvas, $centerX, $metaBaseline - 50, $accent, 96, 'center');

        $this->ribbon($canvas, $payload->ribbon, $centerX, 484, $accent, 'center');
        $this->brandMark($canvas, $payload);

        return $canvas;
    }
}
