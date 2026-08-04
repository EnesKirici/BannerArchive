<?php

namespace App\Services\Trailer\Templates;

use App\Services\Trailer\ThumbnailPayload;
use App\Support\Image\Canvas;

interface ThumbnailTemplate
{
    /** Ayarlarda ve panelde kullanılan kısa anahtar. */
    public function key(): string;

    /** Panelde gösterilecek Türkçe ad. */
    public function label(): string;

    /** Bu şablonun ihtiyaç duyduğu görseller elimizde var mı? */
    public function supports(ThumbnailPayload $payload): bool;

    public function render(ThumbnailPayload $payload): Canvas;
}
