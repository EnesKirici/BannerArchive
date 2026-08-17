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

    /** Kapağın hedef biçimi: 'video' (16:9) ya da 'shorts' (9:16 dikey). */
    public function format(): string;

    /** Bu şablonun ihtiyaç duyduğu görseller elimizde var mı? */
    public function supports(ThumbnailPayload $payload): bool;

    public function render(ThumbnailPayload $payload): Canvas;
}
