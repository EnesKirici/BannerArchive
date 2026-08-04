<?php

namespace App\Console\Commands;

use App\Services\Trailer\ArtworkFetcher;
use App\Services\Trailer\ThumbnailComposer;
use Illuminate\Console\Command;

/**
 * Panelden önce şablonları hızlı denemek için: TMDB kimliğinden kapak üretir.
 *
 * Örnek: php artisan trailer:thumbnail 1241982 --tag="@kanaladi"
 */
class TrailerThumbnailCommand extends Command
{
    protected $signature = 'trailer:thumbnail
        {id : TMDB kimliği}
        {--type=movie : movie veya tv}
        {--template= : Yalnızca bu şablonu üret}
        {--ribbon= : Şerit metni}
        {--accent= : Vurgu rengi (#rrggbb); verilmezse afişten çıkarılır}
        {--tag= : Sağ alt köşe etiketi}
        {--out= : Çıktı klasörü}';

    protected $description = 'TMDB verisinden 1280x720 YouTube kapağı üretir';

    public function handle(ArtworkFetcher $artwork, ThumbnailComposer $composer): int
    {
        $payload = $artwork->fetch((string) $this->option('type'), (int) $this->argument('id'));

        if ($payload === null) {
            $this->components->error('TMDB kaydı alınamadı — kimliği ve bağlantıyı kontrol edin.');

            return self::FAILURE;
        }

        $payload = $payload->with(
            ribbon: $this->option('ribbon'),
            accent: $this->option('accent'),
            tag: $this->option('tag'),
        );

        $this->components->twoColumnDetail('<fg=cyan>Yapım</>', $payload->title.($payload->meta ? "  ({$payload->meta})" : ''));
        $this->components->twoColumnDetail('<fg=cyan>Görseller</>', implode('   ', [
            'afiş '.($payload->poster ? '✓' : '✗'),
            'backdrop '.($payload->backdrop ? '✓' : '✗'),
            'logo '.($payload->logo ? '✓' : '✗'),
        ]));

        $requested = $this->option('template');
        $keys = $requested ? [$requested] : $composer->availableFor($payload);

        if ($keys === []) {
            $this->components->error('Kapak üretmek için yeterli görsel yok.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($keys as $key) {
            $started = microtime(true);

            try {
                $path = $composer->render($payload, $key, $this->option('out') ?: null);
            } catch (\Throwable $exception) {
                $rows[] = [$key, '—', '—', '<fg=red>'.$exception->getMessage().'</>'];

                continue;
            }

            $rows[] = [
                $key,
                number_format(filesize($path) / 1024).' KB',
                number_format((microtime(true) - $started) * 1000).' ms',
                $path,
            ];
        }

        $this->newLine();
        $this->table(['Şablon', 'Boyut', 'Süre', 'Dosya'], $rows);

        return self::SUCCESS;
    }
}
