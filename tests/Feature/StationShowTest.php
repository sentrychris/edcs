<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemBody;
use App\Models\SystemStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_station_by_slug(): void
    {
        $station = SystemStation::factory()->create();

        $response = $this->getJson("/api/stations/{$station->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.name', $station->name);
        $response->assertJsonPath('data.slug', $station->slug);
    }

    public function test_returns_404_when_station_not_found(): void
    {
        $response = $this->getJson('/api/stations/999999-nonexistent');

        $response->assertNotFound();
    }

    public function test_response_includes_correct_structure(): void
    {
        $station = SystemStation::factory()->create();

        $response = $this->getJson("/api/stations/{$station->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'name',
                'body',
                'distance_to_arrival',
                'controlling_faction',
                'allegiance',
                'government',
                'economy',
                'second_economy',
                'has_market',
                'has_shipyard',
                'has_outfitting',
                'other_services',
                'last_updated' => ['information', 'market', 'shipyard', 'outfitting'],
                'slug',
            ],
        ]);
    }

    public function test_loads_system_with_bodies_when_requested(): void
    {
        $system = System::factory()->create();
        $body = SystemBody::factory()->create(['system_id' => $system->id]);
        $station = SystemStation::factory()->create(['system_id' => $system->id]);

        $response = $this->getJson("/api/stations/{$station->slug}?withSystem=1");

        $response->assertOk();
        $response->assertJsonPath('data.system.name', $system->name);
        $bodyNames = collect($response->json('data.system.bodies'))->pluck('name')->all();
        $this->assertContains($body->name, $bodyNames);
    }

    public function test_does_not_load_system_when_not_requested(): void
    {
        $station = SystemStation::factory()->create();

        $response = $this->getJson("/api/stations/{$station->slug}");

        $response->assertOk();
        $this->assertEmpty($response->json('data.system'));
    }

    public function test_boolean_fields_are_cast_correctly(): void
    {
        $station = SystemStation::factory()->create([
            'has_market' => true,
            'has_shipyard' => false,
            'has_outfitting' => true,
        ]);

        $response = $this->getJson("/api/stations/{$station->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.has_market', true);
        $response->assertJsonPath('data.has_shipyard', false);
        $response->assertJsonPath('data.has_outfitting', true);
    }

    public function test_returns_correct_station_data(): void
    {
        $station = SystemStation::factory()->create([
            'type' => 'Coriolis Starport',
            'allegiance' => 'Federation',
            'economy' => 'Industrial',
        ]);

        $response = $this->getJson("/api/stations/{$station->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.type', 'Coriolis Starport');
        $response->assertJsonPath('data.allegiance', 'Federation');
        $response->assertJsonPath('data.economy', 'Industrial');
    }

    public function test_soft_deleted_station_is_not_found(): void
    {
        $station = SystemStation::factory()->create();
        $station->delete();

        $response = $this->getJson("/api/stations/{$station->slug}");

        $response->assertNotFound();
    }
}
