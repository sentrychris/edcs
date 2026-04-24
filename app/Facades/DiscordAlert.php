<?php

namespace App\Facades;

use App\Services\DiscordAlertService;
use Illuminate\Support\Facades\Facade;

class DiscordAlert extends Facade
{
    protected static function getFacadeAccessor()
    {
        return DiscordAlertService::class;
    }
}
