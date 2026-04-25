<?php

namespace Tests\Feature;

use App\Models\System;
use App\Services\Eddn\EddnMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class EddnMarketIndexTest extends TestCase
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

    private function buildBatch(string $systemName, string $stationName, array $commodities): array
    {
        return [
            'messages' => [
                [
                    '$schemaRef' => 'https://eddn.edcd.io/schemas/commodity/3',
                    'header' => ['softwareName' => 'EDO Materials Helper', 'softwareVersion' => '1.0'],
                    'message' => [
                        'systemName' => $systemName,
                        'stationName' => $stationName,
                        'commodities' => $commodities,
                    ],
                ],
            ],
        ];
    }

    public function test_index_is_populated_when_market_data_arrives(): void
    {
        $system = System::factory()->create(['name' => 'Sol']);

        app(EddnMarketService::class)->process($this->buildBatch('Sol', 'Daedalus', [
            ['name' => 'gold', 'buyPrice' => 44000, 'sellPrice' => 44500, 'stock' => 100, 'demand' => 50, 'meanPrice' => 44200],
            ['name' => 'silver', 'buyPrice' => 0, 'sellPrice' => 5000, 'stock' => 0, 'demand' => 200, 'meanPrice' => 4800],
        ]));

        $member = "{$system->id64}:Daedalus";

        $this->assertSame((float) 44000, (float) Redis::zscore('commodity:gold:buy', $member));
        $this->assertSame((float) 44500, (float) Redis::zscore('commodity:gold:sell', $member));

        // silver has no stock, so it should not be in the buy index
        $this->assertNull(Redis::zscore('commodity:silver:buy', $member));
        $this->assertSame((float) 5000, (float) Redis::zscore('commodity:silver:sell', $member));

        $this->assertContains('gold', Redis::smembers('commodities:indexed'));
        $this->assertContains('silver', Redis::smembers('commodities:indexed'));
        $this->assertEqualsCanonicalizing(['gold', 'silver'], Redis::smembers("station:{$system->id64}:Daedalus:commodities"));
    }

    public function test_index_removes_commodities_a_station_no_longer_trades(): void
    {
        $system = System::factory()->create(['name' => 'Sol']);
        $service = app(EddnMarketService::class);

        $service->process($this->buildBatch('Sol', 'Daedalus', [
            ['name' => 'gold', 'buyPrice' => 44000, 'sellPrice' => 44500, 'stock' => 100, 'demand' => 50, 'meanPrice' => 44200],
            ['name' => 'tritium', 'buyPrice' => 50000, 'sellPrice' => 60000, 'stock' => 200, 'demand' => 100, 'meanPrice' => 55000],
        ]));

        $member = "{$system->id64}:Daedalus";
        $this->assertNotNull(Redis::zscore('commodity:tritium:buy', $member));

        // Re-import without tritium — the station no longer trades it
        $service->process($this->buildBatch('Sol', 'Daedalus', [
            ['name' => 'gold', 'buyPrice' => 43000, 'sellPrice' => 43500, 'stock' => 150, 'demand' => 50, 'meanPrice' => 44200],
        ]));

        $this->assertNull(Redis::zscore('commodity:tritium:buy', $member));
        $this->assertNull(Redis::zscore('commodity:tritium:sell', $member));
        $this->assertSame((float) 43000, (float) Redis::zscore('commodity:gold:buy', $member));
        $this->assertEqualsCanonicalizing(['gold'], Redis::smembers("station:{$system->id64}:Daedalus:commodities"));
    }

    public function test_index_drops_commodity_when_stock_drops_to_zero(): void
    {
        $system = System::factory()->create(['name' => 'Sol']);
        $service = app(EddnMarketService::class);

        $service->process($this->buildBatch('Sol', 'Daedalus', [
            ['name' => 'gold', 'buyPrice' => 44000, 'sellPrice' => 44500, 'stock' => 100, 'demand' => 50, 'meanPrice' => 44200],
        ]));

        $member = "{$system->id64}:Daedalus";
        $this->assertNotNull(Redis::zscore('commodity:gold:buy', $member));

        // Stock dropped to zero — buy listing should be pruned, sell listing remains
        $service->process($this->buildBatch('Sol', 'Daedalus', [
            ['name' => 'gold', 'buyPrice' => 44000, 'sellPrice' => 44500, 'stock' => 0, 'demand' => 50, 'meanPrice' => 44200],
        ]));

        $this->assertNull(Redis::zscore('commodity:gold:buy', $member));
        $this->assertNotNull(Redis::zscore('commodity:gold:sell', $member));
    }
}
