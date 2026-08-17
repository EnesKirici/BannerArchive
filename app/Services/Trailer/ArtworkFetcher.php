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
        $data = $this->details($type, $id);

        if ($data === null) {
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
            accent: config('trailer.defaults.accent'),
            brandWhite: (bool) config('trailer.brand.white', false),
            logoLanguage: $logo['file'] !== null ? $logo['language'] : null,
        );
    }

    /**
     * Panelde elle seçilebilsin diye tüm arka plan seçenekleri.
     *
     * Yazısız (dili olmayan) görseller başa alınır: film adını logo olarak
     * biz basıyoruz, üzerinde yazı olan backdrop'lar çakışır.
     *
     * @return array<int, array{path: string, dil: string|null}>
     */
    public function backdrops(string $type, int $id, int $limit = 100): array
    {
        // Yazısız (dilsiz) olanlar başa: film adını logo olarak biz basıyoruz.
        return $this->pickable($this->allImages($type, $id)['backdrops'] ?? [], $limit, fn (?string $language): int => $language === null ? 0 : 1);
    }

    /**
     * Elle seçilebilsin diye tüm film adı logoları (title treatment).
     *
     * Sıra: Türkçe, İngilizce, sonra diğerleri.
     *
     * @return array<int, array{path: string, dil: string|null}>
     */
    public function logos(string $type, int $id, int $limit = 40): array
    {
        return $this->pickable($this->allImages($type, $id)['logos'] ?? [], $limit, fn (?string $language): int => match ($language) {
            'tr' => 0,
            'en' => 1,
            default => 2,
        });
    }

    /**
     * Ortak süzgeç: GD'nin açabildiklerini al, verilen dil önceliğine göre sırala.
     *
     * @param  callable(string|null): int  $rank
     * @return array<int, array{path: string, dil: string|null}>
     */
    private function pickable(mixed $images, int $limit, callable $rank): array
    {
        return collect(is_array($images) ? $images : [])
            ->filter(fn (mixed $image): bool => is_array($image)
                && is_string($image['file_path'] ?? null)
                && in_array(strtolower(pathinfo($image['file_path'], PATHINFO_EXTENSION)), self::DECODABLE, true))
            ->sortBy(fn (array $image): array => [
                $rank($image['iso_639_1'] ?? null),
                -(float) ($image['vote_average'] ?? 0),
            ])
            ->take($limit)
            ->map(fn (array $image): array => [
                'path' => (string) $image['file_path'],
                'dil' => $image['iso_639_1'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Yapımın TÜM görselleri — dil süzgeci olmadan.
     *
     * fetch() `include_image_language=tr,en,null` ile çağırıyor ve diğer
     * dillerdeki görseller listeye hiç girmiyor. Seçim ekranında hepsini
     * göstermek için ayrı, süzgeçsiz bir çağrı gerekiyor.
     *
     * @return array<string, mixed>
     */
    private function allImages(string $type, int $id): array
    {
        $type = in_array($type, ['movie', 'tv'], true) ? $type : 'movie';

        $data = $this->tmdb->remember(
            "trailer_images_{$type}_{$id}",
            now()->addHours(6),
            fn () => $this->tmdb->get("/{$type}/{$id}/images"),
        );

        return is_array($data) ? $data : [];
    }

    /**
     * TMDB kaydı — görselleriyle birlikte, 6 saat önbellekli.
     *
     * @return array<string, mixed>|null
     */
    private function details(string $type, int $id): ?array
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

        return is_array($data) && $data !== [] ? $data : null;
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
