<?php

namespace App\Services\Trailer;

/**
 * Bir kapağın üretilmesi için gereken her şey.
 *
 * Görsel alanları TMDB'den indirilmiş **yerel dosya yollarıdır**; şablonlar
 * ağ erişimi yapmaz. Böylece TMDB kesildiğinde bile önceden indirilmiş
 * görsellerle kapak üretilmeye devam edilebilir.
 */
final readonly class ThumbnailPayload
{
    public function __construct(
        public string $title,
        public ?string $poster = null,
        public ?string $backdrop = null,
        public ?string $logo = null,
        public string $ribbon = 'Türkçe Altyazılı Fragman',
        public ?string $meta = null,
        public ?string $tag = null,
        public ?string $accent = null,
        /** Kullanılan logonun dili ('tr', 'en'…). Panelde uyarı göstermek için. */
        public ?string $logoLanguage = null,
    ) {}

    /** Boş ya da null değerler yok sayılarak üzerine yazılmış bir kopya üret. */
    public function with(
        ?string $ribbon = null,
        ?string $accent = null,
        ?string $tag = null,
        ?string $meta = null,
        ?string $title = null,
    ): self {
        return $this->copy(
            title: self::pick($title, $this->title),
            ribbon: self::pick($ribbon, $this->ribbon),
            meta: self::pick($meta, $this->meta),
            tag: self::pick($tag, $this->tag),
            accent: self::pick($accent, $this->accent),
        );
    }

    /** Logoyu bırak, başlık metnine düş. */
    public function withoutLogo(): self
    {
        return $this->copy(logo: null, dropLogo: true);
    }

    /** Yıl • tür satırını gizle. */
    public function withoutMeta(): self
    {
        return $this->copy(meta: null, dropMeta: true);
    }

    public function hasArtwork(): bool
    {
        return $this->poster !== null || $this->backdrop !== null;
    }

    private function copy(
        ?string $title = null,
        ?string $logo = null,
        ?string $ribbon = null,
        ?string $meta = null,
        ?string $tag = null,
        ?string $accent = null,
        bool $dropLogo = false,
        bool $dropMeta = false,
    ): self {
        return new self(
            title: $title ?? $this->title,
            poster: $this->poster,
            backdrop: $this->backdrop,
            logo: $dropLogo ? null : ($logo ?? $this->logo),
            ribbon: $ribbon ?? $this->ribbon,
            meta: $dropMeta ? null : ($meta ?? $this->meta),
            tag: $tag ?? $this->tag,
            accent: $accent ?? $this->accent,
            logoLanguage: $this->logoLanguage,
        );
    }

    private static function pick(?string $value, ?string $fallback): ?string
    {
        return $value !== null && trim($value) !== '' ? $value : $fallback;
    }
}
