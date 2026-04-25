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

                        $this->updateCommodityIndex($system->id64, $station, $commodities);
                    }
                }
            } catch (\Exception $e) {
                $message = 'Failed to insert market data';
                Log::channel('eddn')->error($message, ['error' => $e->getMessage()]);
                DiscordAlert::eddn(self::class, $message.': '.$e->getMessage(), false);
            }
        }
    }

    /**
     * Maintain the per-commodity inverted index for trade-route searches.
     *
     * Keeps two sorted sets per commodity (one keyed by buy price, one by sell
     * price) so the search endpoints can pull "lowest buy" / "highest sell" in
     * O(log N), plus a per-station bookkeeping set used to prune commodities a
     * station no longer trades.
     *
     * @param  array<int, array<string, mixed>>  $commodities
     */
    private function updateCommodityIndex(int $id64, string $station, array $commodities): void
    {
        $member = "{$id64}:{$station}";
        $stationKey = "station:{$id64}:{$station}:commodities";

        $previous = Redis::smembers($stationKey);
        $current = [];

        foreach ($commodities as $commodity) {
            $name = $commodity['name'] ?? null;
            if ($name === null) {
                continue;
            }

            $buyPrice = (int) ($commodity['buyPrice'] ?? 0);
            $sellPrice = (int) ($commodity['sellPrice'] ?? 0);
            $stock = (int) ($commodity['stock'] ?? 0);
            $demand = (int) ($commodity['demand'] ?? 0);

            $tracked = false;

            if ($buyPrice > 0 && $stock > 0) {
                Redis::zadd("commodity:{$name}:buy", $buyPrice, $member);
                $tracked = true;
            } else {
                Redis::zrem("commodity:{$name}:buy", $member);
            }

            if ($sellPrice > 0 && $demand > 0) {
                Redis::zadd("commodity:{$name}:sell", $sellPrice, $member);
                $tracked = true;
            } else {
                Redis::zrem("commodity:{$name}:sell", $member);
            }

            if ($tracked) {
                $current[] = $name;
                Redis::sadd('commodities:indexed', $name);
            }
        }

        foreach (array_diff($previous, $current) as $name) {
            Redis::zrem("commodity:{$name}:buy", $member);
            Redis::zrem("commodity:{$name}:sell", $member);
        }

        Redis::del($stationKey);
        if ($current !== []) {
            Redis::sadd($stationKey, ...$current);
        }
    }
}
