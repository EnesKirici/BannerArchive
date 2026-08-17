<?php

namespace App\Services\Trailer\Templates;

use App\Services\Trailer\ThumbnailPayload;
use App\Support\Image\Canvas;

/**
 * "Shorts • Afiş Kartı" — 9:16 dikey kapak; afiş kart olarak üstte, metin altta.
 *
 * Zemin afişin bulanık hâli olduğu için backdrop'a ihtiyaç duymaz; dikeyde
 * çalışan en garantili şablon budur.
 */
final class ShortsPosterTemplate extends AbstractTemplate
{
    public function key(): string
    {
        return 'shorts_poster';
    }

    public function label(): string
    {
        return 'Shorts • Afiş Kartı';
    }

    public function format(): string
    {
        return 'shorts';
    }

    public function supports(ThumbnailPayload $payload): bool
    {
        return $payload->poster !== null;
    }

    public function render(ThumbnailPayload $payload): Canvas
    {
        $width = $this->width();
        $height = $this->height();
        $accent = $this->accentOf($payload);

        $canvas = Canvas::create($width, $height, '#04060c', 1.0)
            ->place($this->blurredPoster($payload->poster), 0, 0);

        $canvas->overlay('#05070f', 0.34);
        $canvas->gradient(0, $height - 780, $width, 780, '#02040a', 0.0, 0.92, 'v', 1.6);
        $canvas->vignette(0.50, 0.42);

        $cardHeight = 940;
        $cardWidth = $this->posterWidth($payload->poster, $cardHeight);
        $cardX = (int) round(($width - $cardWidth) / 2);

        $this->posterCard($canvas, $payload->poster, $cardX, 160, $cardHeight, 26);

        $centerX = (int) round($width / 2);

        $titleHeight = $this->titleBlock($canvas, $payload, $centerX, 1560, $width - 180, 280, 'center');

        $metaBaseline = 1560 - $titleHeight - 40;
        $this->metaLine($canvas, $payload->meta, $centerX, $metaBaseline, 'center');
        $this->accentBar($canvas, $centerX, $metaBaseline - 54, $accent, 96, 'center');

        $this->ribbon($canvas, $payload->ribbon, $centerX, 1600, $accent, 'center', 1.3);
        $this->brandMark($canvas, $payload);

        return $canvas;
    }
}
