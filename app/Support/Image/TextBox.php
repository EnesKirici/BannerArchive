<?php

namespace App\Support\Image;

use App\Support\Text\TurkishCase;
use RuntimeException;

/**
 * TTF metin çizimi: ölçme, satır kaydırma, harf aralığı ve kutuya sığdırma.
 *
 * GD metni taban çizgisinden (baseline) çizer. Buradaki draw() ise metin
 * bloğunun **üst kenarını** hizalar — şablonlarda yerleşim hesabı böylesi
 * çok daha kolay. Taban çizgisi gereken yerlerde drawBaseline() kullanılır.
 */
final class TextBox
{
    private string $color = '#ffffff';

    private float $tracking = 0.0;

    private float $lineHeight = 1.12;

    private bool $uppercase = false;

    /** @var array{dx: int, dy: int, hex: string, opacity: float}|null */
    private ?array $shadow = null;

    private function __construct(private readonly string $font, private float $size) {}

    public static function make(string $font, float $size): self
    {
        if (! is_file($font)) {
            throw new RuntimeException("Yazı tipi bulunamadı: {$font}");
        }

        return new self($font, $size);
    }

    public function color(string $hex): self
    {
        $this->color = $hex;

        return $this;
    }

    /** Harf aralığı (piksel). Büyük harfli başlıklarda nefes aldırır. */
    public function tracking(float $tracking): self
    {
        $this->tracking = $tracking;

        return $this;
    }

    public function lineHeight(float $lineHeight): self
    {
        $this->lineHeight = $lineHeight;

        return $this;
    }

    public function uppercase(bool $uppercase = true): self
    {
        $this->uppercase = $uppercase;

        return $this;
    }

    public function shadow(int $dx, int $dy, string $hex = '#000000', float $opacity = 0.5): self
    {
        $this->shadow = ['dx' => $dx, 'dy' => $dy, 'hex' => $hex, 'opacity' => $opacity];

        return $this;
    }

    public function size(): float
    {
        return $this->size;
    }

    /**
     * Tek satırın mürekkep ölçüleri.
     *
     * @return array{width: int, capHeight: int, depth: int, inkHeight: int}
     */
    public function measure(string $text): array
    {
        $text = $this->prepare($text);
        $box = imagettfbbox($this->size, 0, $this->font, $text);

        if ($box === false) {
            throw new RuntimeException('Metin ölçülemedi.');
        }

        return [
            'width' => (int) round($this->widthOf($text)),
            'capHeight' => (int) round(-$box[7]),
            'depth' => (int) round($box[1]),
            'inkHeight' => (int) round($box[1] - $box[7]),
        ];
    }

    /** @return array<int, string> */
    public function wrap(string $text, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', $this->prepare($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current === '' || $this->widthOf($candidate) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * Metin verilen kutuya sığana kadar puntoyu küçült.
     *
     * $maxHeight verilirse satır sayısı sınırının yanı sıra bloğun toplam
     * yüksekliği de gözetilir — aksi hâlde iki satırlık başlık üstündeki
     * içeriğin üzerine taşar.
     */
    public function fit(
        string $text,
        int $maxWidth,
        int $maxLines = 3,
        float $minSize = 18.0,
        ?int $maxHeight = null,
    ): self {
        while ($this->size > $minSize) {
            $lines = $this->wrap($text, $maxWidth);
            $overflows = count($lines) > $maxLines;

            foreach ($lines as $line) {
                if ($this->widthOf($line) > $maxWidth) {
                    $overflows = true;

                    break;
                }
            }

            if ($maxHeight !== null && $this->blockHeight(count($lines)) > $maxHeight) {
                $overflows = true;
            }

            if (! $overflows) {
                break;
            }

            $this->size = max($minSize, $this->size - 2);
        }

        return $this;
    }

    public function blockHeight(int $lines): int
    {
        $metrics = $this->metrics();

        return $metrics['ascent'] + $metrics['descent'] + max(0, $lines - 1) * $metrics['step'];
    }

    /**
     * Metni çiz. ($x, $y) bloğun üst kenarıdır; $align yatay çapayı belirler.
     *
     * @return array{width: int, height: int, lines: int}
     */
    public function draw(
        Canvas $canvas,
        string $text,
        int $x,
        int $y,
        string $align = 'left',
        ?int $maxWidth = null,
    ): array {
        $lines = $maxWidth === null ? [$this->prepare($text)] : $this->wrap($text, $maxWidth);
        $metrics = $this->metrics();

        $widest = 0;

        foreach ($lines as $index => $line) {
            $width = (int) round($this->widthOf($line));
            $widest = max($widest, $width);

            $this->render(
                $canvas,
                $line,
                $this->anchor($x, $width, $align),
                $y + $metrics['ascent'] + $index * $metrics['step'],
            );
        }

        return ['width' => $widest, 'height' => $this->blockHeight(count($lines)), 'lines' => count($lines)];
    }

    /** Tek satırı tam olarak verilen taban çizgisine oturt. */
    public function drawBaseline(Canvas $canvas, string $text, int $x, int $baseline, string $align = 'left'): int
    {
        $text = $this->prepare($text);
        $width = (int) round($this->widthOf($text));

        $this->render($canvas, $text, $this->anchor($x, $width, $align), $baseline);

        return $width;
    }

    private function anchor(int $x, int $width, string $align): int
    {
        return match ($align) {
            'center' => $x - (int) round($width / 2),
            'right' => $x - $width,
            default => $x,
        };
    }

    private function render(Canvas $canvas, string $line, int $x, int $baseline): void
    {
        if ($this->shadow !== null) {
            $this->stroke(
                $canvas,
                $line,
                $x + $this->shadow['dx'],
                $baseline + $this->shadow['dy'],
                Palette::gd($this->shadow['hex'], $this->shadow['opacity']),
            );
        }

        $this->stroke($canvas, $line, $x, $baseline, Palette::gd($this->color, 1.0));
    }

    private function stroke(Canvas $canvas, string $line, int $x, int $baseline, int $color): void
    {
        if ($this->tracking === 0.0) {
            imagettftext($canvas->gd(), $this->size, 0, $x, $baseline, $color, $this->font, $line);

            return;
        }

        // Harf aralığı verildiğinde karakterler tek tek çizilir. İlerleme miktarı
        // kümülatif genişlik farkından alınır; böylece kerning korunur.
        $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $cursor = (float) $x;
        $prefix = '';

        foreach ($characters as $character) {
            imagettftext($canvas->gd(), $this->size, 0, (int) round($cursor), $baseline, $color, $this->font, $character);

            $before = $this->rawWidth($prefix);
            $prefix .= $character;

            $cursor += ($this->rawWidth($prefix) - $before) + $this->tracking;
        }
    }

    /** @return array{ascent: int, descent: int, step: int} */
    private function metrics(): array
    {
        $box = imagettfbbox($this->size, 0, $this->font, 'HÇĞIİOÖŞÜgjpqy');

        if ($box === false) {
            throw new RuntimeException('Yazı tipi ölçüleri okunamadı.');
        }

        $ascent = (int) round(-$box[7]);
        $descent = (int) round($box[1]);

        return [
            'ascent' => $ascent,
            'descent' => $descent,
            'step' => (int) round(($ascent + $descent) * $this->lineHeight),
        ];
    }

    private function widthOf(string $text): float
    {
        $width = $this->rawWidth($text);
        $length = mb_strlen($text);

        return $this->tracking !== 0.0 && $length > 1
            ? $width + $this->tracking * ($length - 1)
            : $width;
    }

    private function rawWidth(string $text): float
    {
        if ($text === '') {
            return 0.0;
        }

        $box = imagettfbbox($this->size, 0, $this->font, $text);

        return $box === false ? 0.0 : (float) ($box[2] - $box[0]);
    }

    private function prepare(string $text): string
    {
        $text = trim($text);

        return $this->uppercase ? TurkishCase::upper($text) : $text;
    }
}
