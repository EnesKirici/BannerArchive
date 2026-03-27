<?php

namespace App\Services;

use ZipArchive;

class ImageConverterService
{
    private const MAX_PIXELS = 25_000_000;

    /**
     * @return array{width: int, height: int, format: string, size: int}
     */
    public function getImageInfo(string $path): array
    {
        $info = getimagesize($path);

        if ($info === false) {
            throw new \RuntimeException('Geçersiz resim dosyası.');
        }

        $format = match ($info[2]) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => throw new \RuntimeException('Desteklenmeyen format.'),
        };

        return [
            'width' => $info[0],
            'height' => $info[1],
            'format' => $format,
            'size' => filesize($path),
        ];
    }

    public function convert(string $sourcePath, string $sourceFormat, string $targetFormat, int $quality): string
    {
        $info = getimagesize($sourcePath);

        if ($info === false) {
            throw new \RuntimeException('Resim dosyası okunamadı.');
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new \RuntimeException('Resim çok büyük (maks 25 megapiksel).');
        }

        $gdImage = match ($sourceFormat) {
            'jpg', 'jpeg' => imagecreatefromjpeg($sourcePath),
            'png' => imagecreatefrompng($sourcePath),
            'webp' => imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($gdImage === false) {
            throw new \RuntimeException('Resim işlenemedi.');
        }

        // PNG/WebP → JPG: şeffaf arka planı beyaz yap
        if ($targetFormat === 'jpg' && in_array($sourceFormat, ['png', 'webp'])) {
            $width = imagesx($gdImage);
            $height = imagesy($gdImage);
            $bg = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopy($bg, $gdImage, 0, 0, 0, 0, $width, $height);
            imagedestroy($gdImage);
            $gdImage = $bg;
        }

        // PNG/JPG → WebP veya PNG: alpha kanalını koru
        if (in_array($targetFormat, ['png', 'webp']) && $sourceFormat !== 'jpg') {
            imagesavealpha($gdImage, true);
            imagealphablending($gdImage, false);
        }

        $outputDir = storage_path('app/private/converted');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir.'/'.uniqid('img_').'.'.$targetFormat;

        $result = match ($targetFormat) {
            'jpg' => imagejpeg($gdImage, $outputPath, $quality),
            'png' => imagepng($gdImage, $outputPath, (int) floor((100 - $quality) * 9 / 100)),
            'webp' => imagewebp($gdImage, $outputPath, $quality),
            default => false,
        };

        imagedestroy($gdImage);

        if ($result === false) {
            throw new \RuntimeException('Dönüştürme başarısız oldu.');
        }

        return $outputPath;
    }

    public function createZipFromFiles(array $files): string
    {
        $outputDir = storage_path('app/private/converted');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $zipPath = $outputDir.'/'.uniqid('zip_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('ZIP dosyası oluşturulamadı.');
        }

        foreach ($files as $file) {
            if (isset($file['tempPath']) && file_exists($file['tempPath'])) {
                $extension = $file['convertedFormat'] ?? 'jpg';
                $originalName = pathinfo($file['originalName'], PATHINFO_FILENAME);
                $zip->addFile($file['tempPath'], $originalName.'.'.$extension);
            }
        }

        $zip->close();

        return $zipPath;
    }

    public function cleanup(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
