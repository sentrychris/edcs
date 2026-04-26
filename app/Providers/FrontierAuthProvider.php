<?php

namespace App\Providers;

use App\Services\Frontier\FrontierAuthService;
use App\Services\Frontier\FrontierCApiService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class FrontierAuthProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(FrontierAuthService::class, fn () => new FrontierAuthService);
        $this->app->bind(
            FrontierCApiService::class,
            fn (Application $app) => new FrontierCApiService($app->make(FrontierAuthService::class))
        );
    }
}
