<?php

namespace App\Services\Trailer\Templates;

use App\Services\Trailer\ThumbnailPayload;
use App\Support\Image\Canvas;

/**
 * "Afiş Odaklı" — zemin afişin bulanık hâli, afiş sağda büyük, metin solda.
 *
 * Backdrop'a hiç ihtiyaç duymaz. Dağıtımcıdan sadece karakter afişleri
 * geldiğinde (elimizde sahne görseli yokken) çalışan şablon budur.
 */
final class PosterFocusTemplate extends AbstractTemplate
{
    public function key(): string
    {
        return 'poster_focus';
    }

    public function label(): string
    {
        return 'Afiş Odaklı';
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
        $canvas->gradient(0, 0, $width, $height, '#02040a', 0.88, 0.05, 'h', 1.3);
        $canvas->vignette(0.55, 0.38);

        $cardHeight = 600;
        $cardTop = (int) round(($height - $cardHeight) / 2);
        $cardWidth = $this->posterWidth($payload->poster, $cardHeight);
        $cardX = $width - 88 - $cardWidth;

        $this->posterCard($canvas, $payload->poster, $cardX, $cardTop, $cardHeight, 22);

        $columnX = 88;
        $columnWidth = $cardX - 60 - $columnX;

        $titleHeight = $this->titleBlock($canvas, $payload, $columnX, 440, $columnWidth, 200);

        $metaBaseline = 440 - $titleHeight - 34;
        $this->metaLine($canvas, $payload->meta, $columnX, $metaBaseline);
        $this->accentBar($canvas, $columnX, $metaBaseline - 48, $accent);

        $this->ribbon($canvas, $payload->ribbon, $columnX, 468, $accent);
        // Afiş sağda durduğu için logo sol alta gider.
        $this->brandMark($canvas, $payload->brand, 'left');

        return $canvas;
    }
}
