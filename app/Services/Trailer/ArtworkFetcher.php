<?php

namespace App\Services\Trailer;

use App\Services\TmdbClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TMDB'den bir yapımın kapak malzemesini toplar: afiş, backdrop ve resmî logo.
 *
 * Görseller diske indirilip yeniden kullanılır; aynı film için ikinci kez
 * kapak üretirken ağa hiç çıkılmaz. TMDB'ye erişilemediğinde TmdbClient
 * bayat kopyayı sunar, indirilmiş görseller de yerinde durduğu için üretim
 * kesintiden etkilenmez.
 */
final class ArtworkFetcher
{
    /**
     * GD'nin açabildiği formatlar. TMDB logolarının önemli bir kısmı SVG'dir
     * ve doğrudan işlenemez; bu yüzden seçim aşamasında elenirler.
     */
    private const DECODABLE = ['png', 'jpg', 'jpeg', 'webp'];

    public function __construct(private readonly TmdbClient $tmdb) {}

    public function fetch(string $type, int $id): ?ThumbnailPayload
    {
        $type = in_array($type, ['movie', 'tv'], true) ? $type : 'movie';

        $data = $this->tmdb->remember(
            "trailer_artwork_{$type}_{$id}",
            now()->addHours(6),
            fn () => $this->tmdb->get("/{$type}/{$id}", [
                'language' => 'tr-TR',
                'append_to_response' => 'images',
                'include_image_language' => 'tr,en,null',
            ]),
        );

        if (! is_array($data) || $data === []) {
            return null;
        }

        $images = is_array($data['images'] ?? null) ? $data['images'] : [];

        // Afiş: Türkçe varsa o (yerel başlık basılı olur), sonra İngilizce.
        $poster = $this->grab($images['posters'] ?? [], ['tr', 'en', null], $data['poster_path'] ?? null, 'poster');
        // Backdrop: önce yazısız (dil = null) olan — logoyu biz koyacağız.
        $backdrop = $this->grab($images['backdrops'] ?? [], [null, 'en', 'tr'], $data['backdrop_path'] ?? null, 'backdrop');
        $logo = $this->grab($images['logos'] ?? [], ['tr', 'en'], null, 'logo');

        return new ThumbnailPayload(
            title: (string) ($data['title'] ?? $data['name'] ?? $data['original_title'] ?? 'İsimsiz'),
            poster: $poster['file'],
            backdrop: $backdrop['file'],
            logo: $logo['file'],
            ribbon: (string) config('trailer.defaults.ribbon'),
            meta: $this->meta($data),
            tag: config('trailer.defaults.tag'),
            accent: config('trailer.defaults.accent'),
            logoLanguage: $logo['file'] !== null ? $logo['language'] : null,
        );
    }

    /**
     * @param  array<int, mixed>  $images
     * @param  array<int, string|null>  $languages
     * @return array{file: string|null, language: string|null}
     */
    private function grab(array $images, array $languages, ?string $fallbackPath, string $sizeKey): array
    {
        $chosen = $this->pick($images, $languages);

        return [
            'file' => $this->download($chosen['path'] ?? $fallbackPath, (string) config("trailer.sizes.{$sizeKey}")),
            'language' => $chosen['language'] ?? null,
        ];
    }

    /**
     * Dil önceliğine göre en iyi görseli seç; eşitlikte oy ortalaması, sonra genişlik.
     *
     * @param  array<int, mixed>  $images
     * @param  array<int, string|null>  $languages
     * @return array{path: string, language: string|null}|null
     */
    private function pick(array $images, array $languages): ?array
    {
        $images = array_values(array_filter(
            $images,
            static fn (mixed $image): bool => is_array($image)
                && is_string($image['file_path'] ?? null)
                && in_array(strtolower(pathinfo($image['file_path'], PATHINFO_EXTENSION)), self::DECODABLE, true),
        ));

        foreach ($languages as $language) {
            $matches = array_values(array_filter(
                $images,
                static fn (array $image): bool => ($image['iso_639_1'] ?? null) === $language,
            ));

            if ($matches === []) {
                continue;
            }

            usort($matches, static fn (array $a, array $b): int => [
                (float) ($b['vote_average'] ?? 0), (int) ($b['width'] ?? 0),
            ] <=> [
                (float) ($a['vote_average'] ?? 0), (int) ($a['width'] ?? 0),
            ]);

            $path = $matches[0]['file_path'] ?? null;

            if (is_string($path) && $path !== '') {
                return ['path' => $path, 'language' => $language];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $data */
    private function meta(array $data): ?string
    {
        $date = $data['release_date'] ?? $data['first_air_date'] ?? null;
        $year = is_string($date) && $date !== '' ? substr($date, 0, 4) : null;

        $genres = collect(is_array($data['genres'] ?? null) ? $data['genres'] : [])
            ->pluck('name')
            ->filter()
            ->take(2)
            ->implode(' • ');

        $parts = array_values(array_filter([$year, $genres !== '' ? $genres : null]));

        return $parts === [] ? null : implode(' • ', $parts);
    }

    /** TMDB görselini diske indir; zaten varsa tekrar indirme. */
    public function download(?string $path, string $size): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $directory = (string) config('trailer.storage.artwork');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            Log::warning('Görsel klasörü oluşturulamadı', ['klasor' => $directory]);

            return null;
        }

        $file = $directory.DIRECTORY_SEPARATOR.$size.'_'.ltrim(str_replace('/', '', $path), '_');

        if (is_file($file) && filesize($file) > 0) {
            return $file;
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(6)
                ->retry(2, 400, throw: false)
                ->get(rtrim((string) config('trailer.image_base'), '/').'/'.$size.$path);
        } catch (ConnectionException $exception) {
            Log::warning('TMDB görseli indirilemedi', ['gorsel' => $path, 'sebep' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('TMDB görseli indirilemedi', ['gorsel' => $path, 'durum' => $response->status()]);

            return null;
        }

        file_put_contents($file, $response->body());

        return $file;
    }
}
