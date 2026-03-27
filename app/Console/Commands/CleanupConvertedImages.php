<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class CleanupConvertedImages extends Command
{
    protected $signature = 'images:cleanup {--max-age=60 : Maksimum dosya yaşı (dakika)}';

    protected $description = 'Dönüştürülmüş geçici resim dosyalarını temizler';

    public function handle(): int
    {
        $maxAge = (int) $this->option('max-age');
        $directory = storage_path('app/private/converted');

        if (! is_dir($directory)) {
            $this->info('Temizlenecek dizin bulunamadı.');

            return self::SUCCESS;
        }

        $files = File::files($directory);
        $deleted = 0;

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($file->getMTime());

            if ($lastModified->diffInMinutes(now()) > $maxAge) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        $this->info("{$deleted} geçici dosya temizlendi.");

        return self::SUCCESS;
    }
}
