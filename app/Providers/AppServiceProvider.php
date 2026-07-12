<?php

namespace App\Providers;

use App\Contracts\AssessmentScorer;
use App\Services\Imports\Demo\DemoCatalogImporter;
use App\Services\Imports\Demo\DemoInventoryImporter;
use App\Services\Imports\Demo\DemoOrderReturnImporter;
use App\Services\Imports\ImportProviderRegistry;
use App\Services\ReadinessScoringService;
use App\Services\RecommendationEngine;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReadinessScoringService::class, function ($app) {
            $scorers = array_map(
                fn (string $class) => $app->make($class),
                config('scoring.scorers'),
            );

            return new ReadinessScoringService($scorers);
        });

        $this->app->bind(AssessmentScorer::class, ReadinessScoringService::class);

        $this->app->singleton(RecommendationEngine::class, function ($app) {
            $rules = array_map(
                fn (string $class) => $app->make($class),
                config('recommendations.rules'),
            );

            return new RecommendationEngine($rules);
        });

        // Shared across the request and its queued jobs so a provider's importer
        // registration (from later tasks' service providers) is visible to the
        // jobs that consume it.
        $this->app->singleton(ImportProviderRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register the demo provider's importers. This is the only provider
        // registered so far; real providers (Shopify, CSV, ...) register the
        // same way from their own service providers in later milestones.
        $this->app->make(ImportProviderRegistry::class)->register(
            provider: 'demo',
            catalogImporter: $this->app->make(DemoCatalogImporter::class),
            orderReturnImporter: $this->app->make(DemoOrderReturnImporter::class),
            inventoryImporter: $this->app->make(DemoInventoryImporter::class),
        );
    }
}
