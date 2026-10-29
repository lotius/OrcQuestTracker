<?php

namespace App\Providers;

use App\Services\OrcQuestCatalog;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The catalog parses a 6,000-line JSON file and derives item levels; do it once per request.
        $this->app->singleton(OrcQuestCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
