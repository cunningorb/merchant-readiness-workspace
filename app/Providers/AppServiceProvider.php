<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ReadinessScoringService::class, function ($app) {
            $scorers = array_map(
                fn (string $class) => $app->make($class),
                config('scoring.scorers'),
            );

            return new \App\Services\ReadinessScoringService($scorers);
        });

        $this->app->bind(\App\Contracts\AssessmentScorer::class, \App\Services\ReadinessScoringService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
