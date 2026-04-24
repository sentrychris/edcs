<?php

namespace App\Services\Eddn;

use App\Facades\DiscordAlert;
use App\Models\System;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class EddnMarketService extends EddnService
{
    /**
     * Import market data through EDDN.
     *
     * @return void
     */
    public function process(array $batch)
    {
        $this->updateMarketData($batch);
    }

    /**
     * Cache system names with their ID64s.
     *
     * @return void
     */
    public function updateMarketData(array $batch)
    {
        foreach ($batch['messages'] as $receivedMessage) {
            try {
                // Check the software name and version
                if (! $this->isSoftwareAllowed($receivedMessage['header'])) {
                    continue;
                }

                $schemaRef = $receivedMessage['$schemaRef'];

                if ($this->validateSchemaRef($schemaRef) && $schemaRef === 'https://eddn.edcd.io/schemas/commodity/3') {
                    $message = $receivedMessage['message'];
                    if (! isset($message['systemName'])) {
                        continue;
                    }

                    $system = System::whereName($message['systemName'])->first();
                    if ($system && isset($message['stationName']) && isset($message['commodities'])) {
                        $station = str_replace(' ', '_', $message['stationName']);
                        $commodities = $message['commodities'];
                        $prohibited = isset($message['prohibited']) ? $message['prohibited'] : [];

                        Redis::set("{$system->id64}_{$station}_eddn_market_data", json_encode([
                            'station' => $message['stationName'],
                            'system' => $system->name,
                            'commodities' => $commodities,
                            'prohibited' => $prohibited,
                            'last_updated' => now()->toISOString(),
                        ]));
                    }
                }
            } catch (\Exception $e) {
                $message = 'Failed to insert market data';
                Log::channel('eddn')->error($message, ['error' => $e->getMessage()]);
                DiscordAlert::eddn(self::class, $message.': '.$e->getMessage(), false);
            }
        }
    }
}
