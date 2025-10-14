<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FirebaseService;
use App\Services\RatingService;
use App\Services\GameGeneratorService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar servicios como singletons
        $this->app->singleton(FirebaseService::class, function ($app) {
            return new FirebaseService();
        });

        $this->app->singleton(RatingService::class, function ($app) {
            return new RatingService();
        });

        $this->app->singleton(GameGeneratorService::class, function ($app) {
            return new GameGeneratorService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}