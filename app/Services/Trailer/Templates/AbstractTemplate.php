<?php

namespace App\Services\Trailer\Templates;

use App\Services\Trailer\ThumbnailPayload;
use App\Support\Image\Canvas;
use App\Support\Image\Palette;
use App\Support\Image\TextBox;
use Illuminate\Support\Facades\Log;

/**
 * Şablonların paylaştığı çizim parçaları: zemin, afiş kartı, başlık bloğu, şerit.
 *
 * Her şablon bunları farklı bir düzende dizer; ortak görsel dil buradan gelir.
 */
abstract class AbstractTemplate implements ThumbnailTemplate
{
    public function format(): string
    {
        return 'video';
    }

    protected function width(): int
    {
        return $this->format() === 'shorts'
            ? (int) config('trailer.shorts.width', 1080)
            : (int) config('trailer.thumbnail.width', 1280);
    }

    protected function height(): int
    {
        return $this->format() === 'shorts'
            ? (int) config('trailer.shorts.height', 1920)
            : (int) config('trailer.thumbnail.height', 720);
    }

    protected function font(string $key): string
    {
        return (string) config("trailer.fonts.{$key}");
    }

    /**
     * Vurgu rengi: elle verilmemişse afişin baskın renginden çıkarılır.
     *
     * Normalde ThumbnailComposer bunu tur başına bir kez hesaplayıp payload'a
     * yazar; buradaki hesap tekil render'lar için yedektir.
     */
    protected function accentOf(ThumbnailPayload $payload): string
    {
        return $payload->accent !== null && trim($payload->accent) !== ''
            ? $payload->accent
            : Palette::accentFor($payload->poster ?? $payload->backdrop);
    }

    /** Backdrop varsa onu, yoksa afişin bulanıklaştırılmış hâlini zemin yap. */
    protected function background(ThumbnailPayload $payload, float $focusY = 0.42): Canvas
    {
        $canvas = Canvas::create($this->width(), $this->height(), '#04060c', 1.0);

        if ($payload->backdrop !== null) {
            return $canvas->place(
                Canvas::open($payload->backdrop)->cover($this->width(), $this->height(), $focusY, 1.04),
                0, 0
            );
        }

        if ($payload->poster !== null) {
            return $canvas->place($this->blurredPoster($payload->poster), 0, 0);
        }

        return $canvas;
    }

    protected function blurredPoster(string $poster): Canvas
    {
        return Canvas::open($poster)
            ->cover($this->width(), $this->height(), 0.35, 1.25)
            ->blurred(5, 10);
    }

    protected function posterWidth(string $poster, int $height): int
    {
        $image = Canvas::open($poster);

        return max(1, (int) round($height * ($image->width() / $image->height())));
    }

    /** Afişi yuvarlak köşeli, ince kenarlıklı ve gölgeli bir kart olarak yerleştir. */
    protected function posterCard(Canvas $canvas, string $poster, int $x, int $y, int $height, int $radius = 20): int
    {
        $image = Canvas::open($poster);
        $width = max(1, (int) round($height * ($image->width() / $image->height())));

        $canvas->shadow($x + 4, $y + 20, $width, $height, $radius, 46, 0.62);
        $canvas->place(Canvas::create($width + 2, $height + 2, '#ffffff', 0.22)->rounded($radius + 1), $x - 1, $y - 1);
        $canvas->place($image->cover($width, $height)->rounded($radius), $x, $y);

        return $width;
    }

    /**
     * Film adı: TMDB'de resmî logo (title treatment) varsa o, yoksa büyük harfli başlık.
     *
     * ($x, $bottom) bloğun alt kenarını çapalar — üstteki içerikle çakışmasın diye
     * yerleşim hep alttan kurulur.
     */
    protected function titleBlock(
        Canvas $canvas,
        ThumbnailPayload $payload,
        int $x,
        int $bottom,
        int $maxWidth,
        int $maxHeight,
        string $align = 'left',
    ): int {
        if ($payload->titleHidden) {
            return 0;
        }

        // Logo bozuk ya da desteklenmeyen bir formatsa sessizce başlık metnine düşülür.
        if ($payload->logo !== null) {
            try {
                return $this->drawLogo($canvas, $payload->logo, $x, $bottom, $maxWidth, $maxHeight, $align);
            } catch (\Throwable $exception) {
                Log::warning('Kapak logosu kullanılamadı, başlık metnine düşüldü', [
                    'logo' => $payload->logo,
                    'sebep' => $exception->getMessage(),
                ]);
            }
        }

        $title = TextBox::make($this->font('display'), 94)
            ->uppercase()
            ->lineHeight(0.94)
            ->tracking(1.0)
            ->color('#ffffff')
            ->shadow(0, 4, '#000000', 0.55)
            ->fit($payload->title, $maxWidth, 3, 44, $maxHeight);

        $height = $title->blockHeight(count($title->wrap($payload->title, $maxWidth)));
        $title->draw($canvas, $payload->title, $x, $bottom - $height, $align, $maxWidth);

        return $height;
    }

    /**
     * Logolar çoğu zaman beyazdır ve açık zeminde kaybolur; logonun kendi
     * silüetinden yumuşak bir gölge üretip altına koyuyoruz.
     */
    private function drawLogo(Canvas $canvas, string $logo, int $x, int $bottom, int $maxWidth, int $maxHeight, string $align): int
    {
        $image = Canvas::open($logo)->trimTransparent()->contain($maxWidth, $maxHeight);

        $left = match ($align) {
            'center' => $x - (int) round($image->width() / 2),
            'right' => $x - $image->width(),
            default => $x,
        };
        $top = $bottom - $image->height();

        $canvas->shadowFrom($image, $left + 2, $top + 9, 22, 0.6);
        $canvas->place($image, $left, $top);

        return $image->height();
    }

    /**
     * Vurgu şeridi (pill). Genişlik metne göre hesaplanır.
     *
     * @return array{width: int, height: int}
     */
    protected function ribbon(
        Canvas $canvas,
        string $text,
        int $x,
        int $y,
        string $accent,
        string $align = 'left',
        float $scale = 1.0,
    ): array {
        // Şerit istenmemiş: hiç çizme, yerleşim hesabına da girmesin.
        if (trim($text) === '') {
            return ['width' => 0, 'height' => 0];
        }

        $height = (int) round(62 * $scale);
        $paddingX = (int) round(28 * $scale);

        $label = TextBox::make($this->font('condensed'), 34 * $scale)
            ->uppercase()
            ->tracking(3.2 * $scale)
            ->color(Palette::readableOn($accent));

        $metrics = $label->measure($text);
        $width = $metrics['width'] + $paddingX * 2;

        $left = match ($align) {
            'center' => $x - (int) round($width / 2),
            'right' => $x - $width,
            default => $x,
        };

        $canvas->shadow($left, $y + 10, $width, $height, 12, 26, 0.45);
        $canvas->place(Canvas::create($width, $height, $accent, 1.0)->rounded(12), $left, $y);

        $label->drawBaseline(
            $canvas,
            $text,
            $left + $paddingX,
            $y + (int) round(($height + $metrics['capHeight']) / 2),
        );

        return ['width' => $width, 'height' => $height];
    }

    /** Yıl • tür gibi ikincil bilgi satırı. */
    protected function metaLine(Canvas $canvas, ?string $meta, int $x, int $baseline, string $align = 'left'): void
    {
        if ($meta === null || trim($meta) === '') {
            return;
        }

        TextBox::make($this->font('ui_soft'), 25)
            ->uppercase()
            ->tracking(2.6)
            ->color('#dfe3ef')
            ->shadow(0, 2, '#000000', 0.5)
            ->drawBaseline($canvas, $meta, $x, $baseline, $align);
    }

    /**
     * Alt köşedeki Avşar Sinema logosu.
     *
     * $corner afişin bulunduğu tarafa göre seçilir; logo afişin üstüne binmesin.
     * Kaynak logo lacivert olduğu için koyu kapaklarda beyaza boyanır.
     */
    protected function brandMark(Canvas $canvas, ThumbnailPayload $payload, string $corner = 'right'): void
    {
        $path = (string) config('trailer.brand.logo');

        if (! $payload->brand || ! is_file($path)) {
            return;
        }

        $brand = $this->brandLogo($path, $payload->brandWhite);

        if ($brand === null) {
            return;
        }

        $logo = $brand['canvas'];

        // Koyu logo koyu kapakta okunmaz. Rengini bozmak yerine altına beyaz
        // bir zemin koyuyoruz — sinema markalarının kapaklarda yaptığı da bu.
        if ($brand['dark']) {
            $paddingX = 20;
            $paddingY = 13;

            $plateWidth = $logo->width() + $paddingX * 2;
            $plateHeight = $logo->height() + $paddingY * 2;
            $plateX = $corner === 'left' ? 70 : $this->width() - 70 - $plateWidth;
            $plateY = $this->height() - 44 - $plateHeight;

            $canvas->shadow($plateX, $plateY + 8, $plateWidth, $plateHeight, 14, 30, 0.55);
            $canvas->place(Canvas::create($plateWidth, $plateHeight, '#ffffff', 1.0)->rounded(14), $plateX, $plateY);
            $canvas->place($logo, $plateX + $paddingX, $plateY + $paddingY);

            return;
        }

        $x = $corner === 'left' ? 78 : $this->width() - 78 - $logo->width();
        $y = $this->height() - 52 - $logo->height();

        $canvas->shadowFrom($logo, $x + 2, $y + 7, 20, 0.6, '#000000');
        $canvas->place($logo, $x, $y);
    }

    /**
     * Kırpılmış, beyaza boyanmış ve ölçeklenmiş marka logosu.
     *
     * Üç şablonda da aynı logo kullanılıyor; kırpma ve boyama piksel piksel
     * yapıldığı için sonuç istek boyunca saklanır. Yalnızca okunarak
     * kullanıldığından paylaşılması güvenli.
     *
     * @var array<string, array{canvas: Canvas, dark: bool}|null>
     */
    private static array $brandCache = [];

    /** @return array{canvas: Canvas, dark: bool}|null */
    private function brandLogo(string $path, bool $white): ?array
    {
        $height = (int) config('trailer.brand.height', 54);
        $key = $path.'|'.(@filemtime($path) ?: 0).'|'.$height.'|'.($white ? 'w' : 'o');

        if (array_key_exists($key, self::$brandCache)) {
            return self::$brandCache[$key];
        }

        try {
            $logo = Canvas::open($path)->trimTransparent();

            if ($white) {
                $logo->recolor('#ffffff');
            }

            $logo = $logo->contain(420, $height);

            return self::$brandCache[$key] = [
                'canvas' => $logo,
                'dark' => Palette::brightness($logo) < 0.45,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Marka logosu kullanılamadı', ['sebep' => $exception->getMessage()]);

            return self::$brandCache[$key] = null;
        }
    }

    /** Vurgu renginde kısa bir çizgi — metin bloğunu başlatan görsel işaret. */
    protected function accentBar(Canvas $canvas, int $x, int $y, string $accent, int $width = 78, string $align = 'left'): void
    {
        $left = match ($align) {
            'center' => $x - (int) round($width / 2),
            'right' => $x - $width,
            default => $x,
        };

        $canvas->place(Canvas::create($width, 6, $accent, 1.0)->rounded(3), $left, $y);
    }
}
