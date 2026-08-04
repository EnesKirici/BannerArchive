<?php

namespace App\Support\Image;

/**
 * Renk yardımcıları: hex ayrıştırma, GD renk üretimi, afişten vurgu rengi çıkarma.
 *
 * GD'de renk 32 bitlik bir tam sayıdır: 0xAARRGGBB. Alfa kanalı terstir —
 * 0 tamamen opak, 127 tamamen şeffaftır.
 */
final class Palette
{
    /** Vurgu rengi bulunamazsa kullanılacak sinema kırmızısı. */
    public const FALLBACK_ACCENT = '#d92044';

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [0, 0, 0];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function hex(int $red, int $green, int $blue): string
    {
        return sprintf(
            '#%02x%02x%02x',
            self::clamp($red),
            self::clamp($green),
            self::clamp($blue)
        );
    }

    /**
     * GD'nin beklediği renk tam sayısını üret.
     *
     * @param  float  $opacity  0 (görünmez) – 1 (tam opak)
     */
    public static function gd(string $hex, float $opacity = 1.0): int
    {
        [$red, $green, $blue] = self::rgb($hex);

        $alpha = (int) round(127 - max(0.0, min(1.0, $opacity)) * 127);

        return ($alpha << 24) | ($red << 16) | ($green << 8) | $blue;
    }

    /** Bir rengin üzerine yazılacak metin için okunaklı renk (beyaz ya da koyu). */
    public static function readableOn(string $background): string
    {
        return self::luminance($background) > 0.55 ? '#101118' : '#ffffff';
    }

    /** WCAG göreli parlaklık (0 siyah – 1 beyaz). */
    public static function luminance(string $hex): float
    {
        $channels = array_map(static function (int $value): float {
            $normalized = $value / 255;

            return $normalized <= 0.03928
                ? $normalized / 12.92
                : (($normalized + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** Rengi aydınlat (pozitif) ya da karart (negatif). $amount: -1 – 1 */
    public static function shift(string $hex, float $amount): string
    {
        [$red, $green, $blue] = self::rgb($hex);

        $target = $amount >= 0 ? 255 : 0;
        $ratio = min(1.0, abs($amount));

        return self::hex(
            (int) round($red + ($target - $red) * $ratio),
            (int) round($green + ($target - $green) * $ratio),
            (int) round($blue + ($target - $blue) * $ratio),
        );
    }

    /**
     * Görselin en baskın *canlı* rengini bul.
     *
     * Gri, çok koyu ve patlamış pikseller elenir; kalanlar ton (hue) kovalarına
     * dağıtılır ve en ağır kova kazanır. Böylece afişin siyah zemini değil,
     * gerçekten göze çarpan rengi seçilir.
     */
    public static function accent(Canvas $image, string $fallback = self::FALLBACK_ACCENT): string
    {
        $width = 48;
        $height = 72;
        $sample = $image->resized($width, $height)->gd();

        /** @var array<int, array{weight: float, red: float, green: float, blue: float}> $buckets */
        $buckets = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($sample, $x, $y);
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;

                $max = max($red, $green, $blue);
                $min = min($red, $green, $blue);

                $value = $max / 255;
                $saturation = $max === 0 ? 0.0 : ($max - $min) / $max;

                if ($saturation < 0.30 || $value < 0.22 || $value > 0.97) {
                    continue;
                }

                $bucket = (int) floor(self::hue($red, $green, $blue) / 15);
                $weight = $saturation * $value;

                $buckets[$bucket] ??= ['weight' => 0.0, 'red' => 0.0, 'green' => 0.0, 'blue' => 0.0];
                $buckets[$bucket]['weight'] += $weight;
                $buckets[$bucket]['red'] += $red * $weight;
                $buckets[$bucket]['green'] += $green * $weight;
                $buckets[$bucket]['blue'] += $blue * $weight;
            }
        }

        if ($buckets === []) {
            return $fallback;
        }

        usort($buckets, static fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);
        $winner = $buckets[0];

        return self::vivid(self::hex(
            (int) round($winner['red'] / $winner['weight']),
            (int) round($winner['green'] / $winner['weight']),
            (int) round($winner['blue'] / $winner['weight']),
        ));
    }

    /**
     * Rengi şerit zemini olarak kullanılabilir hâle getir: doygunluğu artır,
     * fazla açık ya da fazla koyu ise parlaklığı bandın içine çek.
     */
    private static function vivid(string $hex): string
    {
        [$red, $green, $blue] = self::rgb($hex);

        $gray = ($red + $green + $blue) / 3;
        $red = self::clamp((int) round($gray + ($red - $gray) * 1.45));
        $green = self::clamp((int) round($gray + ($green - $gray) * 1.45));
        $blue = self::clamp((int) round($gray + ($blue - $gray) * 1.45));

        $result = self::hex($red, $green, $blue);
        $luminance = self::luminance($result);

        return match (true) {
            $luminance > 0.62 => self::shift($result, -0.28),
            $luminance < 0.08 => self::shift($result, 0.30),
            default => $result,
        };
    }

    /** 0–360 arası ton açısı. */
    private static function hue(int $red, int $green, int $blue): float
    {
        $r = $red / 255;
        $g = $green / 255;
        $b = $blue / 255;

        $max = max($r, $g, $b);
        $delta = $max - min($r, $g, $b);

        if ($delta < 1.0e-9) {
            return 0.0;
        }

        $hue = match (true) {
            abs($max - $r) < 1.0e-9 => fmod(($g - $b) / $delta, 6.0),
            abs($max - $g) < 1.0e-9 => (($b - $r) / $delta) + 2.0,
            default => (($r - $g) / $delta) + 4.0,
        } * 60.0;

        return $hue < 0 ? $hue + 360.0 : $hue;
    }

    private static function clamp(int $value): int
    {
        return max(0, min(255, $value));
    }
}
