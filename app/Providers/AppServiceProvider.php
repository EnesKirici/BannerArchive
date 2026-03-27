<?php

namespace App\Providers;

use App\Services\QuoteGeneratorService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(QuoteGeneratorService::class, function () {
            return new QuoteGeneratorService(
                apiKey: (string) config('services.gemini.api_key'),
                models: (array) config('services.gemini.models', []),
                baseUrl: (string) config('services.gemini.base_url'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Arama: guest 10/dk, auth 60/dk
        RateLimiter::for('search', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        // Galeri/resim görüntüleme: guest 20/dk, auth 120/dk
        RateLimiter::for('browse', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by($request->user()->id)
                : Limit::perMinute(20)->by($request->ip());
        });

        // Proxy (indirme): guest 30/dk, auth 200/dk
        RateLimiter::for('download', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(200)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Quote üretme: guest 3/dk, auth 15/dk
        RateLimiter::for('quotes', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(15)->by($request->user()->id)
                : Limit::perMinute(3)->by($request->ip());
        });

        // Resim dönüştürme: guest 10/dk, auth 30/dk
        RateLimiter::for('image-convert', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(30)->by($request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });
    }
}
