<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateQuotesRequest;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\QuoteGeneratorService;
use App\Services\TmdbClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class TMDBController extends Controller
{
    public function __construct(private readonly TmdbClient $tmdb) {}

    public function gallery(Request $request, string $type, int $id): View
    {
        $data = $this->tmdb->remember("tmdb_{$type}_{$id}_full", now()->addHours(6), fn () => $this->tmdb->get("/{$type}/{$id}", [
            'language' => 'tr-TR',
            'append_to_response' => 'images,credits,watch/providers,videos',
            'include_image_language' => 'tr,en,null',
        ]));

        if (! $data) {
            abort($this->tmdb->unavailable() ? 503 : 404);
        }

        $images = [
            'backdrops' => $data['images']['backdrops'] ?? [],
            'posters' => $data['images']['posters'] ?? [],
            'logos' => $data['images']['logos'] ?? [],
        ];

        $credits = [
            'cast' => array_slice($data['credits']['cast'] ?? [], 0, 30),
        ];

        $watchProviders = $data['watch/providers']['results']['TR'] ?? [];

        $videos = collect($data['videos']['results'] ?? [])
            ->filter(fn ($v) => $v['site'] === 'YouTube' && in_array($v['type'], ['Trailer', 'Teaser', 'Clip', 'Featurette']))
            ->sortByDesc(fn ($v) => $v['type'] === 'Trailer' ? 1 : 0)
            ->values()
            ->take(10)
            ->all();

        $movie = [
            'id' => $data['id'],
            'title' => $type === 'movie' ? ($data['title'] ?? '') : ($data['name'] ?? ''),
            'overview' => $data['overview'] ?? '',
            'poster_path' => $data['poster_path'] ?? '',
            'backdrop_path' => $data['backdrop_path'] ?? '',
            'vote_average' => $data['vote_average'] ?? 0,
            'release_date' => $type === 'movie' ? ($data['release_date'] ?? null) : ($data['first_air_date'] ?? null),
            'type' => $type === 'movie' ? 'Film' : 'Dizi',
            'raw_type' => $type,
        ];

        $particlesLayer = (string) Setting::get('particles_layer', 'background');

        ActivityLog::log($request, 'gallery', $movie['title'], [
            'type' => $type,
            'tmdb_id' => $id,
        ]);

        return view('gallery', compact('movie', 'images', 'credits', 'watchProviders', 'videos', 'particlesLayer'));
    }

    public function index(): View
    {
        $popularMovies = collect($this->trending('movie'));
        $popularShows = collect($this->trending('tv'));

        $galleryViewMode = (string) Setting::get('gallery_view_mode', 'gallery');
        $particlesLayer = (string) Setting::get('particles_layer', 'background');

        return view('home', compact('popularMovies', 'popularShows', 'galleryViewMode', 'particlesLayer'));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');

        if (! $query) {
            return response()->json(['results' => []]);
        }

        ActivityLog::log($request, 'search', $query);

        $cacheKey = 'tmdb_search_'.md5($query);

        $results = $this->tmdb->remember($cacheKey, now()->addMinutes(30), function () use ($query) {
            $data = $this->tmdb->get('/search/multi', [
                'query' => $query,
                'language' => 'tr-TR',
                'include_adult' => false,
            ]);

            if ($data === null) {
                return null;
            }

            $items = array_filter($data['results'] ?? [], function ($item) {
                return ! empty($item['backdrop_path']) && $item['media_type'] !== 'person';
            });

            return array_values(array_map(fn ($item) => $this->formatItem($item, $item['media_type']), $items));
        });

        if ($results === null) {
            return response()->json(['error' => 'TMDB\'ye şu an ulaşılamıyor. Lütfen birazdan tekrar deneyin.'], 503);
        }

        return response()->json(['results' => $results]);
    }

    public function images(string $type, int $id): JsonResponse
    {
        if (! in_array($type, ['movie', 'tv'])) {
            return response()->json(['error' => 'Geçersiz tür'], 400);
        }

        $data = $this->tmdb->remember(
            "tmdb_{$type}_{$id}_images",
            now()->addHours(6),
            fn () => $this->tmdb->get("/{$type}/{$id}/images", ['include_image_language' => 'tr,en,null']),
            default: ['backdrops' => [], 'posters' => [], 'logos' => []],
        );

        return response()->json([
            'backdrops' => $data['backdrops'] ?? [],
            'posters' => $data['posters'] ?? [],
            'logos' => $data['logos'] ?? [],
        ]);
    }

    public function personCredits(int $id): JsonResponse
    {
        $data = $this->tmdb->remember("tmdb_person_{$id}_credits", now()->addHours(6), function () use ($id) {
            $person = $this->tmdb->get("/person/{$id}", [
                'language' => 'tr-TR',
                'append_to_response' => 'combined_credits',
            ]);

            if ($person === null) {
                return null;
            }

            $cast = $person['combined_credits']['cast'] ?? [];

            usort($cast, fn ($a, $b) => ($b['vote_count'] ?? 0) <=> ($a['vote_count'] ?? 0));

            return [
                'name' => $person['name'] ?? '',
                'profile_path' => $person['profile_path'] ?? null,
                'biography' => $person['biography'] ?? '',
                'birthday' => $person['birthday'] ?? null,
                'place_of_birth' => $person['place_of_birth'] ?? null,
                'known_for_department' => $person['known_for_department'] ?? '',
                'credits' => array_slice(array_map(function ($item) {
                    $isMovie = ($item['media_type'] ?? '') === 'movie';

                    return [
                        'id' => $item['id'],
                        'title' => $isMovie ? ($item['title'] ?? '') : ($item['name'] ?? ''),
                        'poster_path' => $item['poster_path'] ?? null,
                        'backdrop_path' => $item['backdrop_path'] ?? null,
                        'vote_average' => $item['vote_average'] ?? 0,
                        'character' => $item['character'] ?? '',
                        'media_type' => $item['media_type'] ?? '',
                        'release_date' => $isMovie ? ($item['release_date'] ?? null) : ($item['first_air_date'] ?? null),
                        'raw_type' => $isMovie ? 'movie' : 'tv',
                    ];
                }, $cast), 0, 30),
            ];
        });

        if (! $data) {
            return $this->tmdb->unavailable()
                ? response()->json(['error' => 'TMDB\'ye şu an ulaşılamıyor.'], 503)
                : response()->json(['error' => 'Kişi bulunamadı'], 404);
        }

        return response()->json($data);
    }

    public function proxyImage(Request $request): \Illuminate\Http\Response
    {
        $path = $request->input('path');
        $size = $request->input('size', 'original');

        ActivityLog::log($request, 'download', $path ?? '', [
            'size' => $size,
        ]);

        if (! $path || ! preg_match('/^\/[a-zA-Z0-9_]+\.\w+$/', $path)) {
            abort(400, 'Geçersiz dosya yolu');
        }

        $validSizes = ['w45', 'w92', 'w154', 'w185', 'w300', 'w342', 'w500', 'w780', 'w1280', 'original'];
        if (! in_array($size, $validSizes)) {
            abort(400, 'Geçersiz boyut');
        }

        try {
            $response = Http::timeout(30)
                ->connectTimeout(5)
                ->retry(2, 250, throw: false)
                ->get("https://image.tmdb.org/t/p/{$size}{$path}");
        } catch (ConnectionException) {
            abort(502, 'Görsel alınamadı');
        }

        if ($response->successful()) {
            return response($response->body())
                ->header('Content-Type', $response->header('Content-Type'))
                ->header('Cache-Control', 'public, max-age=604800');
        }

        abort(502, 'Görsel alınamadı');
    }

    public function generateQuotes(GenerateQuotesRequest $request, QuoteGeneratorService $service): JsonResponse
    {
        $id = $request->integer('id');
        $type = $request->string('type');
        $title = $request->string('title');

        ActivityLog::log($request, 'quote', (string) $title, [
            'type' => (string) $type,
            'tmdb_id' => $id,
        ]);
        $overview = $request->string('overview');
        $style = $request->string('style', '');
        $regenerate = $request->boolean('regenerate', false);

        $cacheKey = "quotes_{$type}_{$id}";

        if ($regenerate) {
            Cache::forget($cacheKey);
        }

        $quotes = Cache::get($cacheKey);

        if ($quotes === null) {
            $quotes = $service->generateQuotes((string) $title, (string) $overview, (string) $type, (string) $style);

            if (! empty($quotes)) {
                Cache::put($cacheKey, $quotes, now()->addDays(7));
            }
        }

        if (empty($quotes)) {
            $response = ['error' => 'Sözler üretilemedi. Lütfen tekrar deneyin.'];

            if (config('app.debug')) {
                $response['debug'] = $service->getLastError();
            }

            return response()->json($response, 500);
        }

        return response()->json([
            'quotes' => $quotes,
            'model' => $service->getUsedModel(),
        ]);
    }

    /**
     * Günün trend listesi. TMDB'ye ulaşılamazsa bayat kopyaya, o da yoksa boş
     * listeye düşer — ana sayfa her hâlükârda açılır.
     *
     * @return array<int, array<string, mixed>>
     */
    private function trending(string $type): array
    {
        return $this->tmdb->remember(
            "tmdb_trending_{$type}",
            now()->addHours(3),
            function () use ($type) {
                $data = $this->tmdb->get("/trending/{$type}/day", ['language' => 'tr-TR']);

                if ($data === null) {
                    return null;
                }

                return collect($data['results'] ?? [])
                    ->take(10)
                    ->map(fn ($item) => $this->formatItem($item, $type))
                    ->all();
            },
            default: [],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function formatItem(array $item, string $type): array
    {
        $isMovie = $type === 'movie';

        return [
            'id' => $item['id'],
            'title' => $isMovie ? ($item['title'] ?? '') : ($item['name'] ?? ''),
            'overview' => $item['overview'] ?? '',
            'poster_path' => $item['poster_path'],
            'backdrop_path' => $item['backdrop_path'],
            'vote_average' => $item['vote_average'] ?? 0,
            'release_date' => $isMovie ? ($item['release_date'] ?? null) : ($item['first_air_date'] ?? null),
            'type' => $isMovie ? 'Film' : 'Dizi',
            'raw_type' => $isMovie ? 'movie' : 'tv',
        ];
    }
}
