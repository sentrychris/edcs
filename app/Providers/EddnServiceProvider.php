<?php

namespace App\Providers;

use App\Services\Eddn\EddnListenerService;
use App\Services\Eddn\EddnMarketService;
use App\Services\Eddn\EddnSystemService;
use Illuminate\Support\ServiceProvider;

class EddnServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(EddnListenerService::class, fn () => new EddnListenerService);
        $this->app->bind(EddnSystemService::class, fn () => new EddnSystemService);
        $this->app->bind(EddnMarketService::class, fn () => new EddnMarketService);
    }
}
