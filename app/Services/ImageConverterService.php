<?php

namespace App\Services;

use App\Services\Image\AvifCodec;
use ZipArchive;

class ImageConverterService
{
    private const MAX_PIXELS = 25_000_000;

    public function __construct(private readonly AvifCodec $avif) {}

    /**
     * Bu sunucuda gerçekten üretilebilen hedef formatlar.
     *
     * @return array<int, string>
     */
    public function availableTargetFormats(): array
    {
        $formats = ['png', 'webp'];

        if ($this->avif->canEncode()) {
            $formats[] = 'avif';
        }

        $formats[] = 'jpg';

        return $formats;
    }

    /**
     * Dosyanın gerçek bir resim olduğunu derinlemesine doğrula.
     *
     * @return array{valid: bool, reason: string|null}
     */
    public function validateUploadSecurity(string $path, string $clientExtension): array
    {
        $config = config('security.upload');

        // 1. Dosya boyutu kontrolü
        $fileSize = filesize($path);
        if ($fileSize === false || $fileSize > $config['max_size_kb'] * 1024) {
            return ['valid' => false, 'reason' => 'Dosya boyutu limiti aşıldı'];
        }

        // 2. Uzantı kontrolü
        $ext = strtolower($clientExtension);
        if (! in_array($ext, $config['allowed_extensions'], true)) {
            return ['valid' => false, 'reason' => "Yasaklı uzantı: {$ext}"];
        }

        // 3. Tehlikeli dosya imzası kontrolü (magic bytes)
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['valid' => false, 'reason' => 'Dosya okunamadı'];
        }

        $header = fread($handle, 64);
        fclose($handle);

        if ($header === false) {
            return ['valid' => false, 'reason' => 'Dosya başlığı okunamadı'];
        }

        foreach ($config['dangerous_signatures'] as $signature => $description) {
            if (str_starts_with($header, $signature)) {
                return ['valid' => false, 'reason' => "Tehlikeli dosya tespit edildi: {$description}"];
            }
        }

        // 4. PHP kodu taraması (dosya içeriğinde)
        $content = file_get_contents($path, false, null, 0, 8192);
        if ($content !== false) {
            $dangerousPatterns = ['<?php', '<?=', '<script', 'eval(', 'base64_decode(', 'system(', 'exec(', 'passthru('];
            foreach ($dangerousPatterns as $pattern) {
                if (stripos($content, $pattern) !== false) {
                    return ['valid' => false, 'reason' => "Zararlı içerik tespit edildi: {$pattern}"];
                }
            }
        }

        // 5. Gerçek MIME type kontrolü (finfo)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        if (! in_array($mimeType, $config['allowed_mimes'], true)) {
            return ['valid' => false, 'reason' => "Geçersiz MIME tipi: {$mimeType}"];
        }

        // 6. getimagesize ile gerçek resim doğrulaması
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            return ['valid' => false, 'reason' => 'Dosya geçerli bir resim değil (getimagesize başarısız)'];
        }

        $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF];
        if (! in_array($imageInfo[2], $allowedImageTypes, true)) {
            return ['valid' => false, 'reason' => 'Desteklenmeyen resim formatı'];
        }

        return ['valid' => true, 'reason' => null];
    }

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
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_AVIF => 'avif',
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
            'jpg', 'jpeg' => @imagecreatefromjpeg($sourcePath),
            'png' => @imagecreatefrompng($sourcePath),
            'webp' => @imagecreatefromwebp($sourcePath),
            'gif' => @imagecreatefromgif($sourcePath),
            'avif' => $this->avif->decode($sourcePath),
            default => false,
        };

        if (! $gdImage instanceof \GdImage) {
            throw new \RuntimeException('Resim işlenemedi.');
        }

        // PNG/WebP/AVIF → JPG: şeffaf arka planı beyaz yap
        if ($targetFormat === 'jpg' && in_array($sourceFormat, ['png', 'webp', 'avif'])) {
            $width = imagesx($gdImage);
            $height = imagesy($gdImage);
            $bg = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopy($bg, $gdImage, 0, 0, 0, 0, $width, $height);
            imagedestroy($gdImage);
            $gdImage = $bg;
        }

        // PNG/JPG → WebP, PNG veya AVIF: alpha kanalını koru
        if (in_array($targetFormat, ['png', 'webp', 'avif']) && $sourceFormat !== 'jpg') {
            imagesavealpha($gdImage, true);
            imagealphablending($gdImage, false);
        }

        $outputDir = storage_path('app/private/converted');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir.'/'.uniqid('img_').'.'.$targetFormat;

        try {
            if ($targetFormat === 'avif') {
                $this->avif->encode($gdImage, $outputPath, $quality);
                $result = true;
            } else {
                $result = match ($targetFormat) {
                    'jpg' => @imagejpeg($gdImage, $outputPath, $quality),
                    'png' => @imagepng($gdImage, $outputPath, (int) floor((100 - $quality) * 9 / 100)),
                    'webp' => @imagewebp($gdImage, $outputPath, $quality),
                    default => false,
                };
            }
        } finally {
            imagedestroy($gdImage);
        }

        // GD bazı kurulumlarda başarısızlığı sessizce 0 byte dosyayla geçiştirir.
        if ($result === false || ! is_file($outputPath) || filesize($outputPath) === 0) {
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }

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
