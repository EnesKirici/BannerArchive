<?php

namespace App\Services;

use Closure;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TMDB API istemcisi.
 *
 * TMDB'ye erişim zaman zaman kesiliyor (TR'den bağlantı zaman aşımı). Bu sınıf
 * kesinti anında istisna fırlatmak yerine null döner; remember() ise son başarılı
 * yanıtın bayat kopyasını sunarak sayfaların 500 vermesini engeller. Üst üste
 * hata alınırsa devre kesici devreye girer ve her istek 10+ saniye beklemek
 * yerine anında null döner.
 */
class TmdbClient
{
    private const TIMEOUT = 8;

    private const CONNECT_TIMEOUT = 4;

    private const RETRY_TIMES = 2;

    private const RETRY_DELAY_MS = 250;

    /** Devre kesiciyi açan ardışık hata sayısı. */
    private const FAILURE_THRESHOLD = 3;

    /** Devre kesici açık kaldığı süre (dakika). */
    private const CIRCUIT_MINUTES = 2;

    /** Bayat kopyanın saklandığı süre (gün). */
    private const STALE_DAYS = 7;

    private const CIRCUIT_KEY = 'tmdb:circuit_open';

    private const FAILURES_KEY = 'tmdb:failures';

    private string $baseUrl;

    private string $apiKey;

    /** Bu istek boyunca TMDB'ye ulaşılamadıysa true. */
    private bool $unavailable = false;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.tmdb.base_url'), '/');
        $this->apiKey = (string) config('services.tmdb.api_key');
    }

    /**
     * Tek bir TMDB isteği. Başarısızlıkta istisna fırlatmaz, null döner.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        if ($this->circuitOpen()) {
            $this->unavailable = true;

            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->retry(self::RETRY_TIMES, self::RETRY_DELAY_MS, throw: false)
                ->get($this->baseUrl.'/'.ltrim($path, '/'), $query + ['api_key' => $this->apiKey]);
        } catch (ConnectionException $e) {
            $this->recordFailure($path, $e->getMessage());

            return null;
        }

        // 5xx geçici sayılır ve devre kesiciyi besler; 4xx (ör. 404) kalıcıdır.
        if ($response->serverError()) {
            $this->recordFailure($path, 'HTTP '.$response->status());

            return null;
        }

        $this->recordSuccess();

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Önbellekten oku; yoksa $fetch ile getir. Getirme başarısız olursa (null dönerse)
     * son başarılı yanıtın bayat kopyasına, o da yoksa $default'a düşer.
     *
     * @param  Closure(): mixed  $fetch
     */
    public function remember(string $key, DateTimeInterface $ttl, Closure $fetch, mixed $default = null): mixed
    {
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $fetch();

        if ($value !== null) {
            Cache::put($key, $value, $ttl);
            Cache::put($this->staleKey($key), $value, now()->addDays(self::STALE_DAYS));

            return $value;
        }

        return Cache::get($this->staleKey($key), $default);
    }

    /**
     * Bu istek sırasında TMDB'ye ulaşılamadı mı? Çağıranın "kayıt yok" (404) ile
     * "servise ulaşılamıyor" (503) durumlarını ayırt etmesi için.
     */
    public function unavailable(): bool
    {
        return $this->unavailable || $this->circuitOpen();
    }

    private function staleKey(string $key): string
    {
        return $key.':stale';
    }

    private function circuitOpen(): bool
    {
        return (bool) Cache::get(self::CIRCUIT_KEY, false);
    }

    private function recordFailure(string $path, string $reason): void
    {
        $this->unavailable = true;

        $failures = (int) Cache::get(self::FAILURES_KEY, 0) + 1;
        Cache::put(self::FAILURES_KEY, $failures, now()->addMinutes(5));

        if ($failures >= self::FAILURE_THRESHOLD) {
            Cache::put(self::CIRCUIT_KEY, true, now()->addMinutes(self::CIRCUIT_MINUTES));
            Cache::forget(self::FAILURES_KEY);

            Log::warning('TMDB devre kesici açıldı', [
                'path' => $path,
                'reason' => $reason,
                'dakika' => self::CIRCUIT_MINUTES,
            ]);
        }
    }

    private function recordSuccess(): void
    {
        Cache::forget(self::FAILURES_KEY);
    }
}
