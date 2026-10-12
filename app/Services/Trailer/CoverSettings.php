<?php

namespace App\Services\Trailer;

/**
 * Kapak tercihleri: şerit, film adı, vurgu rengi, marka logosu.
 *
 * Kapak Stüdyosu paneli ve MCP aracı (Claude) aynı kuralları uygulasın diye
 * tercihlerin payload'a dönüştürülmesi tek yerde durur.
 */
final readonly class CoverSettings
{
    public function __construct(
        /** Boş metin "şerit basılmasın" demektir. */
        public string $ribbon,
        /** auto = TMDB ne verirse · tr = sadece Türkçe logo · text = logo yerine yazı · yok = ad basılmaz */
        public string $logoMode = 'auto',
        /** Elle yazılan film adı; doluysa logo yerine bu metin basılır. */
        public string $customTitle = '',
        public bool $showMeta = true,
        /** marka = sabit kurumsal renk · oto = afişten çıkar · ozel = $accent */
        public string $accentMode = 'marka',
        public string $accent = '',
        public bool $brand = true,
        public bool $brandWhite = false,
        /** Logo elle seçildiyse "film adı nasıl yazılsın" kuralları uygulanmaz. */
        public bool $logoChosen = false,
    ) {}

    /** Panelin şerit anahtarından basılacak metni çöz: yok → boş, ozel → serbest metin. */
    public static function ribbonFor(string $key, string $custom = ''): string
    {
        return match ($key) {
            'yok' => '',
            'ozel' => trim($custom),
            default => (string) (config('trailer.ribbons')[$key] ?? config('trailer.defaults.ribbon')),
        };
    }

    /**
     * Tercihleri payload'a uygula.
     *
     * @return array{0: ThumbnailPayload, 1: string|null} payload ve kullanıcıya gösterilecek not
     */
    public function apply(ThumbnailPayload $payload, bool $manual = false): array
    {
        $notice = null;

        $payload = $payload->with(brand: $this->brand, brandWhite: $this->brandWhite);

        // with() boş değeri yok saydığı için "şerit olmasın" ayrı ele alınıyor.
        $ribbon = trim($this->ribbon);
        $payload = $ribbon === '' ? $payload->withoutRibbon() : $payload->with(ribbon: $ribbon);

        $payload = match ($this->accentMode) {
            'oto' => $payload->withoutAccent(),
            'ozel' => $payload->with(accent: $this->accent),
            default => $payload->with(accent: (string) config('trailer.defaults.accent')),
        };

        if (! $this->showMeta) {
            $payload = $payload->withoutMeta();
        }

        $customTitle = trim($this->customTitle);

        if ($manual) {
            // Elle modda logo yok; ad yazıldıysa metin basılır, boşsa hiç basılmaz.
            if ($customTitle === '') {
                $payload = $payload->withoutTitle();
                $notice = 'Film adı yazılmadı — kapakta ad basılmıyor.';
            }
        } elseif ($this->logoMode === 'yok') {
            $payload = $payload->withoutTitle();
        } elseif ($customTitle !== '') {
            // Elle yazılan ad her şeyin önünde: logo değil bu metin basılır.
            $payload = $payload->with(title: $customTitle)->withoutLogo();
        } elseif ($this->logoChosen) {
            $notice = 'Logo elle seçildi — otomatik kurallar uygulanmadı.';
        } elseif ($this->logoMode === 'text') {
            $payload = $payload->withoutLogo();
        } elseif ($this->logoMode === 'tr' && $payload->logoLanguage !== 'tr') {
            $payload = $payload->withoutLogo();
            $notice = 'TMDB’de Türkçe logo yok — başlık metni yazıldı.';
        } elseif ($payload->logo !== null && $payload->logoLanguage !== 'tr') {
            $notice = 'TMDB’de Türkçe logo yok, '.mb_strtoupper((string) $payload->logoLanguage).' logo kullanıldı.';
        }

        return [$payload, $notice];
    }
}
