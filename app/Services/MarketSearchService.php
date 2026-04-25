<?php

namespace App\Services;

use App\Models\System;
use App\Models\SystemStation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class MarketSearchService
{
    /**
     * Find the lowest buy-price stations for a commodity.
     *
     * @param  string  $commodity  - the commodity internal name
     * @param  array<int, int>|null  $allowedSystemIds64  - if provided, only include stations in these systems
     * @param  int  $limit  - the maximum number of stations to return
     * @param  int  $minStock  - the minimum stock to require
     * @return Collection<int, array<string, mixed>>
     */
    public function lowestBuy(string $commodity, ?array $allowedSystemIds64, int $limit, int $minStock): Collection
    {
        return $this->resolveCandidates(
            commodity: $commodity,
            members: $this->candidateMembers("commodity:{$commodity}:buy", false, $allowedSystemIds64),
            side: 'buy',
            limit: $limit,
            minThreshold: $minStock,
        );
    }

    /**
     * Find the highest sell-price stations for a commodity.
     *
     * @param  string  $commodity  - the commodity internal name
     * @param  array<int, int>|null  $allowedSystemIds64  - if provided, only include stations in these systems
     * @param  int  $limit  - the maximum number of stations to return
     * @param  int  $minDemand  - the minimum demand to require
     * @return Collection<int, array<string, mixed>>
     */
    public function highestSell(string $commodity, ?array $allowedSystemIds64, int $limit, int $minDemand): Collection
    {
        return $this->resolveCandidates(
            commodity: $commodity,
            members: $this->candidateMembers("commodity:{$commodity}:sell", true, $allowedSystemIds64),
            side: 'sell',
            limit: $limit,
            minThreshold: $minDemand,
        );
    }

    /**
     * List all commodity names that currently have at least one station indexed.
     *
     * @return array<int, string>
     */
    public function indexedCommodities(): array
    {
        return Redis::smembers('commodities:indexed');
    }

    /**
     * Pull candidate index members from a sorted set, optionally constrained by system.
     *
     * @param  array<int, int>|null  $allowedSystemIds64
     * @return array<int, string>
     */
    private function candidateMembers(string $key, bool $reverse, ?array $allowedSystemIds64): array
    {
        $members = $reverse
            ? Redis::zrevrange($key, 0, -1)
            : Redis::zrange($key, 0, -1);

        if ($allowedSystemIds64 === null) {
            return $members;
        }

        $allowed = array_flip(array_map('strval', $allowedSystemIds64));

        return array_values(array_filter($members, function (string $member) use ($allowed) {
            [$id64] = explode(':', $member, 2);

            return isset($allowed[$id64]);
        }));
    }

    /**
     * Resolve a list of index members into station/system entries with live market data.
     *
     * @param  array<int, string>  $members  - sorted index members in `{id64}:{station_name}` format
     * @param  string  $side  - 'buy' or 'sell' — which commodity-level filter applies
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveCandidates(string $commodity, array $members, string $side, int $limit, int $minThreshold): Collection
    {
        if ($members === []) {
            return collect();
        }

        $parsed = [];
        $id64s = [];
        $names = [];

        foreach ($members as $member) {
            [$id64, $stationKey] = explode(':', $member, 2);
            $stationName = str_replace('_', ' ', $stationKey);
            $parsed[] = ['id64' => $id64, 'station_key' => $stationKey, 'station_name' => $stationName];
            $id64s[$id64] = true;
            $names[$stationName] = true;
        }

        $systems = System::whereIn('id64', array_keys($id64s))
            ->select('id', 'id64', 'name', 'slug', 'coords_x', 'coords_y', 'coords_z')
            ->get()
            ->keyBy('id64');

        $stations = SystemStation::whereIn('system_id', $systems->pluck('id')->all())
            ->whereIn('name', array_keys($names))
            ->select('id', 'system_id', 'name', 'slug', 'type', 'distance_to_arrival')
            ->get()
            ->keyBy(fn (SystemStation $s) => $s->system_id.':'.$s->name);

        $entries = collect();

        foreach ($parsed as $row) {
            if ($entries->count() >= $limit) {
                break;
            }

            $system = $systems->get($row['id64']);
            if ($system === null) {
                continue;
            }

            $station = $stations->get($system->id.':'.$row['station_name']);
            if ($station === null) {
                continue;
            }

            $marketData = $this->stationMarketData($row['id64'], $row['station_key']);
            if ($marketData === null) {
                continue;
            }

            $commodityEntry = $this->extractCommodity($marketData, $commodity);
            if ($commodityEntry === null) {
                continue;
            }

            if ($side === 'buy' && (int) ($commodityEntry['stock'] ?? 0) < $minThreshold) {
                continue;
            }

            if ($side === 'sell' && (int) ($commodityEntry['demand'] ?? 0) < $minThreshold) {
                continue;
            }

            $entries->push([
                'station' => $station,
                'system' => $system,
                'commodity' => $commodityEntry,
                'last_updated' => $marketData['last_updated'] ?? null,
            ]);
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stationMarketData(string $id64, string $stationKey): ?array
    {
        $raw = Redis::get("{$id64}_{$stationKey}_eddn_market_data");

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $marketData
     * @return array<string, mixed>|null
     */
    private function extractCommodity(array $marketData, string $commodity): ?array
    {
        foreach ($marketData['commodities'] ?? [] as $entry) {
            if (($entry['name'] ?? null) === $commodity) {
                return $entry;
            }
        }

        return null;
    }
}
