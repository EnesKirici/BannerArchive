<?php

namespace App\Providers;

use App\Services\QuoteGeneratorService;
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
    }
}
