<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MarketTradeRouteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->clearCommodityIndex();
    }

    protected function tearDown(): void
    {
        $this->clearCommodityIndex();
        parent::tearDown();
    }

    private function clearCommodityIndex(): void
    {
        foreach (Redis::keys('commodity:*') as $key) {
            Redis::del($key);
        }
        foreach (Redis::keys('station:*:commodities') as $key) {
            Redis::del($key);
        }
        foreach (Redis::keys('*_eddn_market_data') as $key) {
            Redis::del($key);
        }
        Redis::del('commodities:indexed');
    }

    private function indexStation(System $system, SystemStation $station, array $commodities): void
    {
        $stationKey = str_replace(' ', '_', $station->name);
        $member = "{$system->id64}:{$stationKey}";

        Redis::set("{$system->id64}_{$stationKey}_eddn_market_data", json_encode([
            'station' => $station->name,
            'system' => $system->name,
            'commodities' => $commodities,
            'prohibited' => [],
            'last_updated' => '2026-04-25T10:00:00Z',
        ]));

        foreach ($commodities as $commodity) {
            $name = $commodity['name'];
            $tracked = false;

            if ($commodity['buyPrice'] > 0 && $commodity['stock'] > 0) {
                Redis::zadd("commodity:{$name}:buy", $commodity['buyPrice'], $member);
                $tracked = true;
            }

            if ($commodity['sellPrice'] > 0 && $commodity['demand'] > 0) {
                Redis::zadd("commodity:{$name}:sell", $commodity['sellPrice'], $member);
                $tracked = true;
            }

            if ($tracked) {
                Redis::sadd('commodities:indexed', $name);
                Redis::sadd("station:{$system->id64}:{$stationKey}:commodities", $name);
            }
        }
    }

    public function test_returns_routes_sorted_by_profit_per_unit(): void
    {
        $sol = System::factory()->create(['name' => 'Sol']);
        $alpha = System::factory()->create(['name' => 'Alpha Centauri']);

        $cheapGold = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'GoldSource']);
        $bigGoldBuyer = SystemStation::factory()->create(['system_id' => $alpha->id, 'name' => 'GoldBuyer']);

        $cheapTritium = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'TritiumSource']);
        $bigTritiumBuyer = SystemStation::factory()->create(['system_id' => $alpha->id, 'name' => 'TritiumBuyer']);

        $this->indexStation($sol, $cheapGold, [
            ['name' => 'gold', 'buyPrice' => 30000, 'sellPrice' => 31000, 'stock' => 500, 'demand' => 0, 'meanPrice' => 40000],
        ]);
        $this->indexStation($alpha, $bigGoldBuyer, [
            ['name' => 'gold', 'buyPrice' => 0, 'sellPrice' => 50000, 'stock' => 0, 'demand' => 1000, 'meanPrice' => 40000],
        ]);

        $this->indexStation($sol, $cheapTritium, [
            ['name' => 'tritium', 'buyPrice' => 20000, 'sellPrice' => 21000, 'stock' => 500, 'demand' => 0, 'meanPrice' => 50000],
        ]);
        $this->indexStation($alpha, $bigTritiumBuyer, [
            ['name' => 'tritium', 'buyPrice' => 0, 'sellPrice' => 80000, 'stock' => 0, 'demand' => 1000, 'meanPrice' => 50000],
        ]);

        $response = $this->getJson('/api/stations/search/trade-route');

        $response->assertOk();
        // Tritium has 60k profit, gold has 20k — tritium first
        $response->assertJsonPath('data.0.commodity.name', 'tritium');
        $response->assertJsonPath('data.0.profit_per_unit', 60000);
        $response->assertJsonPath('data.0.buy_from.station.name', 'TritiumSource');
        $response->assertJsonPath('data.0.sell_to.station.name', 'TritiumBuyer');

        $response->assertJsonPath('data.1.commodity.name', 'gold');
        $response->assertJsonPath('data.1.profit_per_unit', 20000);
    }

    public function test_skips_pairings_where_buy_and_sell_are_the_same_station(): void
    {
        $sol = System::factory()->create(['name' => 'Sol']);
        $alpha = System::factory()->create(['name' => 'Alpha Centauri']);

        $bothEnds = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'OnePlace']);
        $otherSeller = SystemStation::factory()->create(['system_id' => $alpha->id, 'name' => 'RealBuyer']);

        // Same station has the lowest buy and the highest sell — should fall back to the next
        $this->indexStation($sol, $bothEnds, [
            ['name' => 'gold', 'buyPrice' => 30000, 'sellPrice' => 90000, 'stock' => 500, 'demand' => 1000, 'meanPrice' => 40000],
        ]);
        $this->indexStation($alpha, $otherSeller, [
            ['name' => 'gold', 'buyPrice' => 0, 'sellPrice' => 50000, 'stock' => 0, 'demand' => 1000, 'meanPrice' => 40000],
        ]);

        $response = $this->getJson('/api/stations/search/trade-route?min_profit=1000');

        $response->assertOk();
        // Falls back to OnePlace -> RealBuyer (50k - 30k = 20k profit)
        $response->assertJsonPath('data.0.buy_from.station.name', 'OnePlace');
        $response->assertJsonPath('data.0.sell_to.station.name', 'RealBuyer');
        $response->assertJsonPath('data.0.profit_per_unit', 20000);
    }

    public function test_min_profit_filter_excludes_low_profit_routes(): void
    {
        $sol = System::factory()->create(['name' => 'Sol']);
        $alpha = System::factory()->create(['name' => 'Alpha Centauri']);

        $src = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'Src']);
        $dst = SystemStation::factory()->create(['system_id' => $alpha->id, 'name' => 'Dst']);

        $this->indexStation($sol, $src, [
            ['name' => 'gold', 'buyPrice' => 49000, 'sellPrice' => 49500, 'stock' => 500, 'demand' => 0, 'meanPrice' => 50000],
        ]);
        $this->indexStation($alpha, $dst, [
            ['name' => 'gold', 'buyPrice' => 0, 'sellPrice' => 50000, 'stock' => 0, 'demand' => 1000, 'meanPrice' => 50000],
        ]);

        // Profit is 1000; min_profit of 5000 should exclude it
        $response = $this->getJson('/api/stations/search/trade-route?min_profit=5000');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    public function test_returns_empty_when_no_indexed_commodities(): void
    {
        $response = $this->getJson('/api/stations/search/trade-route');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }
}
