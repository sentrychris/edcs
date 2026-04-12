<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    private function buildMarketJson(string $station, string $system, array $commodities = [], array $prohibited = []): string
    {
        return json_encode([
            'station' => $station,
            'system' => $system,
            'commodities' => $commodities,
            'prohibited' => $prohibited,
            'last_updated' => '2026-04-12T10:00:00Z',
        ]);
    }

    public function test_returns_market_data_for_station(): void
    {
        $system = System::factory()->create();
        $station = SystemStation::factory()->create([
            'system_id' => $system->id,
            'name' => 'Daedalus',
        ]);

        $marketJson = $this->buildMarketJson('Daedalus', $system->name, [
            (object) ['name' => 'gold', 'buyPrice' => 44000, 'sellPrice' => 44500, 'stock' => 100, 'demand' => 0],
        ]);

        Redis::shouldReceive('get')
            ->once()
            ->with("{$system->id64}_Daedalus_eddn_market_data")
            ->andReturn($marketJson);

        $response = $this->getJson("/api/stations/{$station->slug}/market");

        $response->assertOk();
        $response->assertJsonPath('data.station', 'Daedalus');
        $response->assertJsonPath('data.system', $system->name);
        $response->assertJsonPath('data.last_updated', '2026-04-12T10:00:00Z');
    }

    public function test_returns_empty_data_when_no_market_data_in_redis(): void
    {
        $system = System::factory()->create();
        $station = SystemStation::factory()->create([
            'system_id' => $system->id,
            'name' => 'Hutton Orbital',
        ]);

        Redis::shouldReceive('get')
            ->once()
            ->with("{$system->id64}_Hutton_Orbital_eddn_market_data")
            ->andReturn(null);

        $response = $this->getJson("/api/stations/{$station->slug}/market");

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    public function test_returns_404_when_station_not_found(): void
    {
        $response = $this->getJson('/api/stations/999999-nonexistent/market');

        $response->assertNotFound();
        $response->assertJsonPath('message', 'Station not found.');
    }

    public function test_commodity_names_are_mapped_to_display_names(): void
    {
        $system = System::factory()->create();
        $station = SystemStation::factory()->create([
            'system_id' => $system->id,
            'name' => 'Jameson Memorial',
        ]);

        $marketJson = $this->buildMarketJson('Jameson Memorial', $system->name, [
            (object) ['name' => 'gold', 'buyPrice' => 44000, 'sellPrice' => 44500, 'stock' => 100, 'demand' => 0],
            (object) ['name' => 'advancedcatalysers', 'buyPrice' => 2500, 'sellPrice' => 2800, 'stock' => 50, 'demand' => 10],
        ]);

        Redis::shouldReceive('get')
            ->once()
            ->with("{$system->id64}_Jameson_Memorial_eddn_market_data")
            ->andReturn($marketJson);

        $response = $this->getJson("/api/stations/{$station->slug}/market");

        $response->assertOk();

        // 'advancedcatalysers' should be mapped to 'Advanced Catalysers' via config
        $response->assertJsonPath('data.commodities.advancedcatalysers.name', 'Advanced Catalysers');

        // 'gold' has no config mapping, so it stays as-is
        $response->assertJsonPath('data.commodities.gold.name', config('commodities.gold', 'gold'));
    }

    public function test_returns_prohibited_commodities(): void
    {
        $system = System::factory()->create();
        $station = SystemStation::factory()->create([
            'system_id' => $system->id,
            'name' => 'Ohm City',
        ]);

        $prohibited = ['imperialslaves', 'battleweapons'];
        $marketJson = $this->buildMarketJson('Ohm City', $system->name, [], $prohibited);

        Redis::shouldReceive('get')
            ->once()
            ->with("{$system->id64}_Ohm_City_eddn_market_data")
            ->andReturn($marketJson);

        $response = $this->getJson("/api/stations/{$station->slug}/market");

        $response->assertOk();
        $response->assertJsonPath('data.prohibited', $prohibited);
    }

    public function test_redis_key_uses_correct_format_with_underscored_station_name(): void
    {
        $system = System::factory()->create();
        $station = SystemStation::factory()->create([
            'system_id' => $system->id,
            'name' => 'Ray Gateway',
        ]);

        // Spaces replaced with underscores in the Redis key
        Redis::shouldReceive('get')
            ->once()
            ->with("{$system->id64}_Ray_Gateway_eddn_market_data")
            ->andReturn(null);

        $response = $this->getJson("/api/stations/{$station->slug}/market");

        $response->assertOk();
    }
}
