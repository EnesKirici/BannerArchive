<?php

namespace App\Services\Trailer\Templates;

use App\Services\Trailer\ThumbnailPayload;
use App\Support\Image\Canvas;

/**
 * "Afiş Kartı" — solda afiş, sağda logo ve şerit, zeminde backdrop.
 *
 * En bilgi yoğun şablon: hem afişi hem sahneyi gösterir. Backdrop yoksa
 * zemine afişin bulanık hâli konur, düzen bozulmaz.
 */
final class PosterCardTemplate extends AbstractTemplate
{
    public function key(): string
    {
        return 'poster_card';
    }

    public function label(): string
    {
        return 'Afiş Kartı';
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

        $canvas = $this->background($payload, 0.40);
        $canvas->overlay('#050810', 0.22);
        $canvas->gradient(0, 0, $width, $height, '#02040a', 0.94, 0.0, 'h', 1.35);
        $canvas->gradient(0, $height - 300, $width, 300, '#02040a', 0.0, 0.70, 'v', 1.5);
        $canvas->vignette(0.50, 0.40);

        $cardHeight = 548;
        $cardTop = (int) round(($height - $cardHeight) / 2);
        $cardWidth = $this->posterCard($canvas, $payload->poster, 78, $cardTop, $cardHeight, 20);

        $columnX = 78 + $cardWidth + 64;
        $columnWidth = $width - $columnX - 78;

        // Metin grubu alttan kurulur: başlık kaç satır olursa olsun şerit sabit
        // kalır, meta ve vurgu çizgisi başlığın gerçek yüksekliğine göre yukarı kayar.
        $titleHeight = $this->titleBlock($canvas, $payload, $columnX, 435, $columnWidth, 210);

        $metaBaseline = 435 - $titleHeight - 34;
        $this->metaLine($canvas, $payload->meta, $columnX, $metaBaseline);
        $this->accentBar($canvas, $columnX, $metaBaseline - 48, $accent);

        $this->ribbon($canvas, $payload->ribbon, $columnX, 463, $accent);
        $this->brandMark($canvas, $payload);

        return $canvas;
    }
}
