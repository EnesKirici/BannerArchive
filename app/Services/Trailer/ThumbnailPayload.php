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
        public ?string $accent = null,
        /** Alt köşeye Avşar Sinema logosu basılsın mı? */
        public bool $brand = true,
        /** Logo beyaza boyansın mı? false = kendi rengi (koyuysa beyaz zeminle). */
        public bool $brandWhite = false,
        /** Kullanılan logonun dili ('tr', 'en'…). Panelde uyarı göstermek için. */
        public ?string $logoLanguage = null,
    ) {}

    /** Boş ya da null değerler yok sayılarak üzerine yazılmış bir kopya üret. */
    public function with(
        ?string $ribbon = null,
        ?string $accent = null,
        ?string $meta = null,
        ?string $title = null,
        ?bool $brand = null,
        ?bool $brandWhite = null,
    ): self {
        return $this->copy(
            title: self::pick($title, $this->title),
            ribbon: self::pick($ribbon, $this->ribbon),
            meta: self::pick($meta, $this->meta),
            accent: self::pick($accent, $this->accent),
            brand: $brand ?? $this->brand,
            brandWhite: $brandWhite ?? $this->brandWhite,
        );
    }

    /** Logoyu bırak, başlık metnine düş. */
    public function withoutLogo(): self
    {
        return $this->copy(dropLogo: true);
    }

    /** Yıl • tür satırını gizle. */
    public function withoutMeta(): self
    {
        return $this->copy(dropMeta: true);
    }

    /** Arka planı elle seçilen görselle değiştir (null = arka plan yok). */
    public function withBackdrop(?string $file): self
    {
        return $this->copy(backdrop: $file, setBackdrop: true);
    }

    /** Film adı logosunu elle seçilenle değiştir. */
    public function withLogo(?string $file): self
    {
        return $this->copy(logo: $file, setLogo: true);
    }

    /** Vurgu rengini temizle ki afişin baskın renginden çıkarılsın. */
    public function withoutAccent(): self
    {
        return $this->copy(dropAccent: true);
    }

    public function hasArtwork(): bool
    {
        return $this->poster !== null || $this->backdrop !== null;
    }

    private function copy(
        ?string $title = null,
        ?string $ribbon = null,
        ?string $meta = null,
        ?string $accent = null,
        ?bool $brand = null,
        ?bool $brandWhite = null,
        ?string $backdrop = null,
        bool $setBackdrop = false,
        ?string $logo = null,
        bool $setLogo = false,
        bool $dropLogo = false,
        bool $dropMeta = false,
        bool $dropAccent = false,
    ): self {
        return new self(
            title: $title ?? $this->title,
            poster: $this->poster,
            backdrop: $setBackdrop ? $backdrop : $this->backdrop,
            logo: $dropLogo ? null : ($setLogo ? $logo : $this->logo),
            ribbon: $ribbon ?? $this->ribbon,
            meta: $dropMeta ? null : ($meta ?? $this->meta),
            accent: $dropAccent ? null : ($accent ?? $this->accent),
            brand: $brand ?? $this->brand,
            brandWhite: $brandWhite ?? $this->brandWhite,
            logoLanguage: $this->logoLanguage,
        );
    }

    private static function pick(?string $value, ?string $fallback): ?string
    {
        return $value !== null && trim($value) !== '' ? $value : $fallback;
    }
}
