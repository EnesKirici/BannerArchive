<?php

namespace App\Services\Image;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * AVIF okuma/yazma katmanı.
 *
 * Öncelik sırası:
 *   1. GD'nin yerleşik AVIF desteği (varsa ve GERÇEKTEN çalışıyorsa)
 *   2. libavif-bin araçları (avifdec / avifenc)
 *
 * gd_info()'ya güvenilmez: derleme başlığı AVIF'i ilan etse bile sistem
 * libgd'si libavif olmadan derlenmişse imagecreatefromavif() "AVIF image
 * support has been disabled" der, imageavif() ise sessizce 0 byte dosya
 * üretip true döner. Bu yüzden yetenek runtime'da ÖLÇÜLÜR.
 */
class AvifCodec
{
    private const PROBE_CACHE_KEY = 'image.avif.capabilities';

    /** @var array{native: bool, external: bool}|null */
    private ?array $capabilities = null;

    public function canDecode(): bool
    {
        $caps = $this->capabilities();

        return $caps['native'] || $caps['external'];
    }

    public function canEncode(): bool
    {
        return $this->canDecode();
    }

    /**
     * AVIF dosyasını GD kaynağına çevirir.
     */
    public function decode(string $sourcePath): \GdImage
    {
        $caps = $this->capabilities();

        if ($caps['native']) {
            $image = @imagecreatefromavif($sourcePath);

            if ($image instanceof \GdImage) {
                return $image;
            }
        }

        if (! $caps['external']) {
            throw new \RuntimeException($this->unsupportedMessage());
        }

        $temporaryPng = $this->temporaryPath('png');

        try {
            $this->run([
                $this->decoderPath(),
                '--jobs', 'all',
                '--depth', '8',
                '--png-compress', '1',
                $sourcePath,
                $temporaryPng,
            ]);

            if (! is_file($temporaryPng) || filesize($temporaryPng) === 0) {
                throw new \RuntimeException('AVIF dosyası çözümlenemedi.');
            }

            $image = @imagecreatefrompng($temporaryPng);

            if (! $image instanceof \GdImage) {
                throw new \RuntimeException('AVIF dosyası çözümlenemedi.');
            }

            return $image;
        } finally {
            $this->forget($temporaryPng);
        }
    }

    /**
     * GD kaynağını AVIF olarak diske yazar.
     */
    public function encode(\GdImage $image, string $outputPath, int $quality): void
    {
        $caps = $this->capabilities();
        $quality = max(1, min(100, $quality));

        if ($caps['native'] && @imageavif($image, $outputPath, $quality) && $this->hasContent($outputPath)) {
            return;
        }

        if (! $caps['external']) {
            throw new \RuntimeException($this->unsupportedMessage());
        }

        $temporaryPng = $this->temporaryPath('png');

        try {
            imagesavealpha($image, true);

            if (! @imagepng($image, $temporaryPng, 1)) {
                throw new \RuntimeException('AVIF çıktısı hazırlanamadı.');
            }

            $this->run([
                $this->encoderPath(),
                '--jobs', 'all',
                '--qcolor', (string) $quality,
                '--speed', (string) config('image.avif.speed', 6),
                $temporaryPng,
                $outputPath,
            ]);

            if (! $this->hasContent($outputPath)) {
                throw new \RuntimeException('AVIF çıktısı oluşturulamadı.');
            }
        } finally {
            $this->forget($temporaryPng);
        }
    }

    public function unsupportedMessage(): string
    {
        return 'AVIF desteği bu sunucuda kullanılamıyor.';
    }

    /**
     * @return array{native: bool, external: bool}
     */
    private function capabilities(): array
    {
        if ($this->capabilities !== null) {
            return $this->capabilities;
        }

        $ttl = (int) config('image.avif.probe_ttl', 3600);

        return $this->capabilities = Cache::remember(
            self::PROBE_CACHE_KEY,
            $ttl,
            fn (): array => [
                'native' => $this->probeNative(),
                'external' => $this->probeExternal(),
            ]
        );
    }

    /**
     * GD gerçekten AVIF yazıp okuyabiliyor mu? Sonuç ölçülür, ilan edilmez.
     */
    private function probeNative(): bool
    {
        if (! function_exists('imageavif') || ! function_exists('imagecreatefromavif')) {
            return false;
        }

        $probePath = $this->temporaryPath('avif');
        $canvas = imagecreatetruecolor(16, 16);

        try {
            // 0 byte dosya = libgd'nin sessiz başarısızlığı, boyut kontrolü şart.
            if (! @imageavif($canvas, $probePath, 50) || ! $this->hasContent($probePath)) {
                return false;
            }

            return @imagecreatefromavif($probePath) instanceof \GdImage;
        } finally {
            imagedestroy($canvas);
            $this->forget($probePath);
        }
    }

    private function probeExternal(): bool
    {
        foreach ([$this->decoderPath(), $this->encoderPath()] as $binary) {
            try {
                if (! Process::timeout(10)->run([$binary, '--version'])->successful()) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): void
    {
        $result = Process::timeout((int) config('image.avif.timeout', 30))->run($command);

        if ($result->successful()) {
            return;
        }

        Log::warning('AVIF aracı başarısız oldu.', [
            'binary' => $command[0],
            'exit_code' => $result->exitCode(),
            'error' => mb_substr(trim($result->errorOutput()), 0, 500),
        ]);

        throw new \RuntimeException('AVIF dönüştürme işlemi tamamlanamadı.');
    }

    private function decoderPath(): string
    {
        return (string) config('image.avif.decoder', 'avifdec');
    }

    private function encoderPath(): string
    {
        return (string) config('image.avif.encoder', 'avifenc');
    }

    private function hasContent(string $path): bool
    {
        return is_file($path) && filesize($path) > 0;
    }

    private function temporaryPath(string $extension): string
    {
        return sys_get_temp_dir().'/'.uniqid('avif_', true).'.'.$extension;
    }

    private function forget(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
