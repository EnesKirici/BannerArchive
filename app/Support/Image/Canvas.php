<?php

namespace App\Support\Image;

use GdImage;
use RuntimeException;

/**
 * GD üzerine ince bir tuval sarmalayıcısı.
 *
 * Kural: **dönüştürme** metotları (cover, contain, resized, blurred) yeni bir
 * Canvas döndürür; **çizim** metotları (gradient, overlay, place, rounded...)
 * mevcut tuvali değiştirip $this döndürür. Böylece zincirleme okunaklı kalır.
 */
final class Canvas
{
    /**
     * Çözülmüş görsellerin istek ömrü boyunca saklandığı önbellek.
     *
     * Bir kapak turunda aynı afiş defalarca açılıyor (zemin, kart, renk analizi).
     * JPEG çözmek pahalı, bellekten kopyalamak neredeyse bedava.
     *
     * @var array<string, GdImage>
     */
    private static array $decoded = [];

    private function __construct(private readonly GdImage $image) {}

    /** Boş tuval. $fill verilmezse tamamen şeffaf başlar. */
    public static function create(int $width, int $height, ?string $fill = null, float $opacity = 1.0): self
    {
        $width = max(1, $width);
        $height = max(1, $height);

        $image = imagecreatetruecolor($width, $height);

        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle(
            $image, 0, 0, $width - 1, $height - 1,
            $fill === null ? Palette::gd('#000000', 0.0) : Palette::gd($fill, $opacity)
        );
        imagealphablending($image, true);

        return new self($image);
    }

    public static function open(string $path): self
    {
        $key = $path.'|'.(@filemtime($path) ?: 0);

        if (! isset(self::$decoded[$key])) {
            $binary = @file_get_contents($path);

            if ($binary === false) {
                throw new RuntimeException("Görsel okunamadı: {$path}");
            }

            $image = @imagecreatefromstring($binary);

            if ($image === false) {
                throw new RuntimeException("Görsel çözümlenemedi: {$path}");
            }

            imagealphablending($image, true);
            imagesavealpha($image, true);

            self::$decoded[$key] = $image;
        }

        return new self(self::duplicate(self::$decoded[$key]));
    }

    /** Çözülmüş görsel önbelleğini boşalt (uzun süren işlemler için). */
    public static function flushDecoded(): void
    {
        self::$decoded = [];
    }

    private static function duplicate(GdImage $source): GdImage
    {
        $copy = imagecreatetruecolor(imagesx($source), imagesy($source));

        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagecopy($copy, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
        imagealphablending($copy, true);

        return $copy;
    }

    public function width(): int
    {
        return imagesx($this->image);
    }

    public function height(): int
    {
        return imagesy($this->image);
    }

    public function gd(): GdImage
    {
        return $this->image;
    }

    // ---------------------------------------------------------------- dönüşüm

    /**
     * Hedef ölçüyü ortadan kırparak doldur (CSS'teki object-fit: cover).
     *
     * @param  float  $focusY  0 üst kenar, 0.5 orta, 1 alt kenar
     * @param  float  $zoom    1'den büyük değerler kadraja yaklaştırır
     */
    public function cover(int $width, int $height, float $focusY = 0.5, float $zoom = 1.0): self
    {
        $sourceWidth = $this->width();
        $sourceHeight = $this->height();

        $targetRatio = $width / $height;

        if ($sourceWidth / $sourceHeight > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
        }

        if ($zoom > 1.0) {
            $cropWidth = max(1, (int) round($cropWidth / $zoom));
            $cropHeight = max(1, (int) round($cropHeight / $zoom));
        }

        $x = (int) round(($sourceWidth - $cropWidth) / 2);
        $y = (int) round(($sourceHeight - $cropHeight) * max(0.0, min(1.0, $focusY)));

        $output = self::create($width, $height);

        imagealphablending($output->image, false);
        imagecopyresampled($output->image, $this->image, 0, 0, $x, $y, $width, $height, $cropWidth, $cropHeight);
        imagealphablending($output->image, true);

        return $output;
    }

    /** Oranı bozmadan kutuya sığdır (object-fit: contain). */
    public function contain(int $width, int $height): self
    {
        $scale = min($width / $this->width(), $height / $this->height());

        return $this->resized(
            max(1, (int) round($this->width() * $scale)),
            max(1, (int) round($this->height() * $scale)),
        );
    }

    public function resized(int $width, int $height): self
    {
        $width = max(1, $width);
        $height = max(1, $height);

        $output = self::create($width, $height);

        imagealphablending($output->image, false);
        imagecopyresampled($output->image, $this->image, 0, 0, 0, 0, $width, $height, $this->width(), $this->height());
        imagealphablending($output->image, true);

        return $output;
    }

    /**
     * Tamamen saydam kenar boşluklarını kırp.
     *
     * TMDB logolarının etrafında çoğu zaman geniş bir boşluk olur; kırpmadan
     * yerleştirilirse logo olduğundan küçük ve kaymış görünür.
     */
    public function trimTransparent(int $threshold = 120): self
    {
        $width = $this->width();
        $height = $this->height();

        $left = $width;
        $right = -1;
        $top = $height;
        $bottom = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (((imagecolorat($this->image, $x, $y) >> 24) & 0x7F) >= $threshold) {
                    continue;
                }

                $left = min($left, $x);
                $right = max($right, $x);
                $top = min($top, $y);
                $bottom = max($bottom, $y);
            }
        }

        if ($right < 0) {
            return $this;
        }

        $cropped = self::create($right - $left + 1, $bottom - $top + 1);

        imagealphablending($cropped->image, false);
        imagecopy($cropped->image, $this->image, 0, 0, $left, $top, $right - $left + 1, $bottom - $top + 1);
        imagealphablending($cropped->image, true);

        return $cropped;
    }

    /**
     * Yumuşak bulanıklık.
     *
     * GD'nin gauss filtresi tek başına çok zayıf kalır; görsel önce küçültülüp
     * filtre birkaç kez uygulanır, sonra geri büyütülür. Hem daha güzel hem de
     * tam ölçekte filtre çalıştırmaktan kat kat hızlı.
     */
    public function blurred(int $passes = 3, int $downscale = 6): self
    {
        $width = $this->width();
        $height = $this->height();

        $small = $this->resized(
            max(1, (int) round($width / max(1, $downscale))),
            max(1, (int) round($height / max(1, $downscale))),
        );

        for ($pass = 0; $pass < $passes; $pass++) {
            imagefilter($small->image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        return $small->resized($width, $height);
    }

    // ------------------------------------------------------------------ çizim

    /** Köşeleri yumuşat (kenar yumuşatmalı). */
    public function rounded(int $radius): self
    {
        $width = $this->width();
        $height = $this->height();
        $radius = max(0, min($radius, (int) floor(min($width, $height) / 2)));

        if ($radius === 0) {
            return $this;
        }

        imagealphablending($this->image, false);

        // [çember merkezi x, çember merkezi y, taranacak alanın sol üst köşesi]
        $corners = [
            [$radius, $radius, 0, 0],
            [$width - 1 - $radius, $radius, $width - $radius, 0],
            [$radius, $height - 1 - $radius, 0, $height - $radius],
            [$width - 1 - $radius, $height - 1 - $radius, $width - $radius, $height - $radius],
        ];

        foreach ($corners as [$centerX, $centerY, $originX, $originY]) {
            for ($y = 0; $y < $radius; $y++) {
                for ($x = 0; $x < $radius; $x++) {
                    $pixelX = $originX + $x;
                    $pixelY = $originY + $y;

                    $distance = sqrt(($pixelX - $centerX) ** 2 + ($pixelY - $centerY) ** 2);
                    $coverage = max(0.0, min(1.0, $radius - $distance + 0.5));

                    if ($coverage >= 1.0) {
                        continue;
                    }

                    $color = imagecolorat($this->image, $pixelX, $pixelY);
                    $alpha = ($color >> 24) & 0x7F;

                    $faded = (int) round(127 - $coverage * (127 - $alpha));

                    imagesetpixel($this->image, $pixelX, $pixelY, ($faded << 24) | ($color & 0xFFFFFF));
                }
            }
        }

        imagealphablending($this->image, true);

        return $this;
    }

    /**
     * Tüm pikselleri tek renge boya, saydamlığı koru.
     *
     * Tek renkli logolar için: lacivert marka logosu koyu kapakta kaybolmasın
     * diye beyaza çevrilir, kenar yumuşatması bozulmaz.
     */
    public function recolor(string $hex): self
    {
        [$red, $green, $blue] = Palette::rgb($hex);
        $rgb = ($red << 16) | ($green << 8) | $blue;

        imagealphablending($this->image, false);

        for ($y = 0; $y < $this->height(); $y++) {
            for ($x = 0; $x < $this->width(); $x++) {
                imagesetpixel($this->image, $x, $y, (imagecolorat($this->image, $x, $y) & 0x7F000000) | $rgb);
            }
        }

        imagealphablending($this->image, true);

        return $this;
    }

    public function place(self $source, int $x, int $y): self
    {
        imagecopy($this->image, $source->image, $x, $y, 0, 0, $source->width(), $source->height());

        return $this;
    }

    public function placeCentered(self $source, int $centerX, int $centerY): self
    {
        return $this->place(
            $source,
            $centerX - (int) round($source->width() / 2),
            $centerY - (int) round($source->height() / 2),
        );
    }

    /** Tüm tuvale düz renk tabakası uygula. */
    public function overlay(string $hex, float $opacity): self
    {
        imagefilledrectangle(
            $this->image, 0, 0, $this->width() - 1, $this->height() - 1,
            Palette::gd($hex, $opacity)
        );

        return $this;
    }

    /**
     * Doğrusal geçiş (gradient).
     *
     * @param  string  $direction  'v' dikey, 'h' yatay
     * @param  float   $ease       1 doğrusal; büyük değerler geçişi sona yığar
     */
    public function gradient(
        int $x,
        int $y,
        int $width,
        int $height,
        string $hex,
        float $from,
        float $to,
        string $direction = 'v',
        float $ease = 1.0,
    ): self {
        $steps = $direction === 'v' ? $height : $width;

        if ($steps <= 0) {
            return $this;
        }

        for ($step = 0; $step < $steps; $step++) {
            $ratio = $steps === 1 ? 1.0 : $step / ($steps - 1);
            $opacity = $from + ($to - $from) * ($ease === 1.0 ? $ratio : $ratio ** $ease);

            if ($opacity <= 0.003) {
                continue;
            }

            $color = Palette::gd($hex, $opacity);

            if ($direction === 'v') {
                imagefilledrectangle($this->image, $x, $y + $step, $x + $width - 1, $y + $step, $color);
            } else {
                imagefilledrectangle($this->image, $x + $step, $y, $x + $step, $y + $height - 1, $color);
            }
        }

        return $this;
    }

    /**
     * Kenarları karartan vinyet.
     *
     * GD'de radyal geçiş yok; küçük bir tabakada piksel piksel hesaplayıp
     * büyütmek hem pürüzsüz sonuç verir hem de neredeyse bedava.
     */
    public function vignette(float $strength = 0.55, float $inner = 0.42, string $hex = '#000000'): self
    {
        $layerWidth = 96;
        $layerHeight = 54;

        $layer = self::create($layerWidth, $layerHeight);
        [$red, $green, $blue] = Palette::rgb($hex);

        imagealphablending($layer->image, false);

        $centerX = ($layerWidth - 1) / 2;
        $centerY = ($layerHeight - 1) / 2;
        $maxDistance = sqrt($centerX ** 2 + $centerY ** 2);

        for ($y = 0; $y < $layerHeight; $y++) {
            for ($x = 0; $x < $layerWidth; $x++) {
                $distance = sqrt(($x - $centerX) ** 2 + ($y - $centerY) ** 2) / $maxDistance;

                $falloff = $distance <= $inner ? 0.0 : (($distance - $inner) / (1 - $inner)) ** 1.7;
                $alpha = (int) round(127 - min(1.0, $falloff) * $strength * 127);

                imagesetpixel($layer->image, $x, $y, ($alpha << 24) | ($red << 16) | ($green << 8) | $blue);
            }
        }

        imagealphablending($layer->image, true);

        return $this->place($layer->resized($this->width(), $this->height()), 0, 0);
    }

    /**
     * Yuvarlak köşeli bir dikdörtgenin yumuşak gölgesi.
     *
     * GD'nin gauss filtresi alfa kanalını bulanıklaştırmaz; merkez pikselin
     * alfasını olduğu gibi taşır. Bu yüzden gölge önce **opak gri tonlamalı bir
     * maske** olarak çizilip bulanıklaştırılır, sonra parlaklık saydamlığa
     * çevrilir. Maske küçük ölçekte hazırlanır: hem hızlı hem daha yumuşak.
     */
    public function shadow(
        int $x,
        int $y,
        int $width,
        int $height,
        int $radius = 0,
        int $blur = 32,
        float $opacity = 0.55,
        string $hex = '#000000',
    ): self {
        $padding = max(6, $blur);
        $scale = max(2, (int) round($blur / 6));

        $mask = self::create(
            max(3, (int) round(($width + $padding * 2) / $scale)),
            max(3, (int) round(($height + $padding * 2) / $scale)),
            '#ffffff',
            1.0,
        );

        $mask->place(
            self::create(
                max(1, (int) round($width / $scale)),
                max(1, (int) round($height / $scale)),
                '#000000',
                1.0,
            )->rounded((int) round($radius / $scale)),
            (int) round($padding / $scale),
            (int) round($padding / $scale),
        );

        return $this->place(
            self::maskToShadow($mask, $hex, $opacity)->resized($width + $padding * 2, $height + $padding * 2),
            $x - $padding,
            $y - $padding,
        );
    }

    /**
     * Bir görselin kendi silüetinden yumuşak gölge üret.
     *
     * Logolar çoğunlukla beyaz PNG'dir ve açık zeminde kaybolur; şeklin
     * kendisinden türeyen gölge onları zemine oturtur.
     */
    public function shadowFrom(
        self $source,
        int $x,
        int $y,
        int $blur = 18,
        float $opacity = 0.5,
        string $hex = '#000000',
    ): self {
        $scale = max(2, (int) round($blur / 5));

        $small = $source->resized(
            max(3, (int) round($source->width() / $scale)),
            max(3, (int) round($source->height() / $scale)),
        );

        $mask = self::create($small->width(), $small->height(), '#ffffff', 1.0);

        imagealphablending($mask->image, false);

        for ($pixelY = 0; $pixelY < $small->height(); $pixelY++) {
            for ($pixelX = 0; $pixelX < $small->width(); $pixelX++) {
                // opak piksel → siyah, şeffaf piksel → beyaz
                $alpha = (imagecolorat($small->image, $pixelX, $pixelY) >> 24) & 0x7F;
                $tone = (int) round($alpha / 127 * 255);

                imagesetpixel($mask->image, $pixelX, $pixelY, ($tone << 16) | ($tone << 8) | $tone);
            }
        }

        imagealphablending($mask->image, true);

        return $this->place(
            self::maskToShadow($mask, $hex, $opacity)->resized($source->width(), $source->height()),
            $x,
            $y,
        );
    }

    /** Gri tonlamalı maskeyi bulanıklaştır, sonra parlaklığı saydamlığa çevir. */
    private static function maskToShadow(self $mask, string $hex, float $opacity): self
    {
        for ($pass = 0; $pass < 3; $pass++) {
            imagefilter($mask->image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        [$red, $green, $blue] = Palette::rgb($hex);

        imagealphablending($mask->image, false);

        for ($y = 0; $y < $mask->height(); $y++) {
            for ($x = 0; $x < $mask->width(); $x++) {
                $tone = imagecolorat($mask->image, $x, $y) & 0xFF;
                $alpha = (int) round(127 - (1 - $tone / 255) * $opacity * 127);

                imagesetpixel($mask->image, $x, $y, ($alpha << 24) | ($red << 16) | ($green << 8) | $blue);
            }
        }

        imagealphablending($mask->image, true);

        return $mask;
    }

    public function adjust(int $brightness = 0, int $contrast = 0): self
    {
        if ($brightness !== 0) {
            imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $brightness);
        }

        if ($contrast !== 0) {
            imagefilter($this->image, IMG_FILTER_CONTRAST, $contrast);
        }

        return $this;
    }

    // ------------------------------------------------------------------ kayıt

    public function toJpeg(string $path, int $quality = 88): string
    {
        $this->ensureDirectory($path);

        // JPEG alfa taşımaz; şeffaf pikseller siyah zemine oturtulur.
        $flattened = self::create($this->width(), $this->height(), '#000000', 1.0)->place($this, 0, 0);

        if (! imagejpeg($flattened->image, $path, $quality)) {
            throw new RuntimeException("JPEG yazılamadı: {$path}");
        }

        return $path;
    }

    public function toPng(string $path): string
    {
        $this->ensureDirectory($path);

        if (! imagepng($this->image, $path)) {
            throw new RuntimeException("PNG yazılamadı: {$path}");
        }

        return $path;
    }

    private function ensureDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Klasör oluşturulamadı: {$directory}");
        }
    }
}
