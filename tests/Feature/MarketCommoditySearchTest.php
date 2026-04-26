<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MarketCommoditySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_returns_lowest_buy_and_highest_sell_for_commodity(): void
    {
        $sol = System::factory()->create(['name' => 'Sol', 'coords_x' => 0, 'coords_y' => 0, 'coords_z' => 0]);
        $alpha = System::factory()->create(['name' => 'Alpha Centauri', 'coords_x' => 4, 'coords_y' => 0, 'coords_z' => 0]);

        $cheap = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'CheapBuy']);
        $expensive = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'PricyBuy']);
        $bestPayer = SystemStation::factory()->create(['system_id' => $alpha->id, 'name' => 'TopSeller']);

        $this->indexStation($sol, $cheap, [
            ['name' => 'gold', 'buyPrice' => 30000, 'sellPrice' => 31000, 'stock' => 500, 'demand' => 0, 'meanPrice' => 40000],
        ]);
        $this->indexStation($sol, $expensive, [
            ['name' => 'gold', 'buyPrice' => 50000, 'sellPrice' => 51000, 'stock' => 200, 'demand' => 100, 'meanPrice' => 40000],
        ]);
        $this->indexStation($alpha, $bestPayer, [
            ['name' => 'gold', 'buyPrice' => 0, 'sellPrice' => 70000, 'stock' => 0, 'demand' => 800, 'meanPrice' => 40000],
        ]);

        $response = $this->getJson('/api/stations/search/commodity?commodity=gold');

        $response->assertOk();
        $response->assertJsonPath('data.commodity.name', 'gold');
        $response->assertJsonPath('data.commodity.display_name', 'Gold');

        $response->assertJsonPath('data.best_buy_from.0.station.name', 'CheapBuy');
        $response->assertJsonPath('data.best_buy_from.0.buy_price', 30000);
        $response->assertJsonPath('data.best_buy_from.1.station.name', 'PricyBuy');

        $response->assertJsonPath('data.best_sell_to.0.station.name', 'TopSeller');
        $response->assertJsonPath('data.best_sell_to.0.sell_price', 70000);
    }

    public function test_filters_results_to_systems_within_ly_of_near_system(): void
    {
        $sol = System::factory()->create(['name' => 'Sol', 'coords_x' => 0, 'coords_y' => 0, 'coords_z' => 0]);
        $colonia = System::factory()->create(['name' => 'Colonia', 'coords_x' => 22000, 'coords_y' => 0, 'coords_z' => 0]);
        $outsideSphere = System::factory()->create(['name' => 'Diagonal Far', 'coords_x' => 80, 'coords_y' => 80, 'coords_z' => 0]);

        $solStation = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'NearStation']);
        $coloniaStation = SystemStation::factory()->create(['system_id' => $colonia->id, 'name' => 'FarStation']);
        $outsideSphereStation = SystemStation::factory()->create(['system_id' => $outsideSphere->id, 'name' => 'OutsideSphereStation']);

        $this->indexStation($sol, $solStation, [
            ['name' => 'gold', 'buyPrice' => 40000, 'sellPrice' => 41000, 'stock' => 500, 'demand' => 100, 'meanPrice' => 40000],
        ]);
        $this->indexStation($colonia, $coloniaStation, [
            ['name' => 'gold', 'buyPrice' => 20000, 'sellPrice' => 80000, 'stock' => 500, 'demand' => 1000, 'meanPrice' => 40000],
        ]);
        $this->indexStation($outsideSphere, $outsideSphereStation, [
            ['name' => 'gold', 'buyPrice' => 10000, 'sellPrice' => 90000, 'stock' => 500, 'demand' => 1000, 'meanPrice' => 40000],
        ]);

        $response = $this->getJson("/api/stations/search/commodity?commodity=gold&near_system={$sol->slug}&ly=100");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.best_buy_from');
        $response->assertJsonPath('data.best_buy_from.0.station.name', 'NearStation');
        $response->assertJsonCount(1, 'data.best_sell_to');
        $response->assertJsonPath('data.best_sell_to.0.station.name', 'NearStation');
    }

    public function test_min_stock_excludes_low_stock_buy_listings(): void
    {
        $sol = System::factory()->create(['name' => 'Sol']);
        $low = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'LowStock']);
        $high = SystemStation::factory()->create(['system_id' => $sol->id, 'name' => 'HighStock']);

        $this->indexStation($sol, $low, [
            ['name' => 'gold', 'buyPrice' => 30000, 'sellPrice' => 0, 'stock' => 5, 'demand' => 0, 'meanPrice' => 40000],
        ]);
        $this->indexStation($sol, $high, [
            ['name' => 'gold', 'buyPrice' => 35000, 'sellPrice' => 0, 'stock' => 1000, 'demand' => 0, 'meanPrice' => 40000],
        ]);

        $response = $this->getJson('/api/stations/search/commodity?commodity=gold&min_stock=100');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.best_buy_from');
        $response->assertJsonPath('data.best_buy_from.0.station.name', 'HighStock');
    }

    public function test_returns_empty_lists_when_no_index_data(): void
    {
        $response = $this->getJson('/api/stations/search/commodity?commodity=tritium');

        $response->assertOk();
        $response->assertJsonPath('data.best_buy_from', []);
        $response->assertJsonPath('data.best_sell_to', []);
    }

    public function test_validates_required_commodity_parameter(): void
    {
        $response = $this->getJson('/api/stations/search/commodity');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('commodity');
    }

    public function test_validates_near_system_must_exist(): void
    {
        $response = $this->getJson('/api/stations/search/commodity?commodity=gold&near_system=does-not-exist');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('near_system');
    }
}
