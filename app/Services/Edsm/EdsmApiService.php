<?php

namespace App\Services\Edsm;

use App\Facades\DiscordAlert;
use App\Services\ApiService;
use DateTime;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

abstract class EdsmApiService extends ApiService
{
    /**
     * Make a GET request against the EDSM API and bump the request counter.
     */
    protected function edsmRequest(string $category, string $key, array $params, ?string $subkey = null): mixed
    {
        $response = $this->setConfig(config('elite.edsm'))
            ->setCategory($category)
            ->get(key: $key, subkey: $subkey, params: $params);

        $this->setApiRequestCounter();

        return $response;
    }

    /**
     * Get the API request counter for EDSM.
     */
    public function getApiRequestCounter(): int
    {
        return Redis::get('edsm_api_called') ?? 0;
    }

    /**
     * Increment the EDSM API request counter.
     */
    protected function setApiRequestCounter(): void
    {
        $counter = Redis::get('edsm_api_called') ?? 0;
        Redis::set('edsm_api_called', $counter + 1, 'EX', 120);
    }

    /**
     * Log an EDSM import failure to the import:system channel and fire a
     * Discord alert tagged with the concrete service class that raised it.
     */
    protected function logAndAlert(string $message): void
    {
        Log::channel('import:system')->error($message);
        DiscordAlert::edsm(static::class, $message, false);
    }

    /**
     * Validate an EDSM-supplied timestamp; fall back to now() if it is
     * malformed or outside the acceptable year window.
     */
    protected function date($date, $format = 'Y-m-d H:i:s', $minYear = 2013, $maxYear = 2026)
    {
        $d = DateTime::createFromFormat($format, $date);

        if ($d && $d->format($format) === $date) {
            $year = (int) $d->format('Y');
            if ($year >= $minYear && $year <= $maxYear) {
                return $date;
            }
        }

        return date('Y-m-d H:i:s');
    }
}
