<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemBody;
use App\Models\SystemStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemBodyShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_body_by_slug(): void
    {
        $body = SystemBody::factory()->create();

        $response = $this->getJson("/api/bodies/{$body->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.name', $body->name);
        $response->assertJsonPath('data.slug', $body->slug);
    }

    public function test_returns_404_when_body_not_found(): void
    {
        $response = $this->getJson('/api/bodies/999999-nonexistent');

        $response->assertNotFound();
    }

    public function test_response_includes_correct_structure(): void
    {
        $body = SystemBody::factory()->create();

        $response = $this->getJson("/api/bodies/{$body->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'body_id',
                'name',
                'type',
                'sub_type',
                'distance_to_arrival',
                'is_main_star',
                'is_scoopable',
                'spectral_class',
                'luminosity',
                'solar_masses',
                'solar_radius',
                'absolute_magnitude',
                'discovery' => ['commander', 'date'],
                'radius',
                'gravity',
                'earth_masses',
                'surface_temp',
                'is_landable',
                'atmosphere_type',
                'volcanism_type',
                'terraforming_state',
                'axial' => ['axial_tilt', 'semi_major_axis', 'rotational_period', 'is_tidally_locked'],
                'orbital' => ['orbital_period', 'orbital_eccentricity', 'orbital_inclination', 'arg_of_periapsis'],
                'rings',
                'parents',
                'slug',
            ],
        ]);
    }

    public function test_loads_system_relation_when_requested(): void
    {
        $system = System::factory()->create();
        $body = SystemBody::factory()->create(['system_id' => $system->id]);

        $response = $this->getJson("/api/bodies/{$body->slug}?withSystem=1");

        $response->assertOk();
        $response->assertJsonPath('data.system.name', $system->name);
    }

    public function test_does_not_load_system_relation_when_not_requested(): void
    {
        $body = SystemBody::factory()->create();

        $response = $this->getJson("/api/bodies/{$body->slug}");

        $response->assertOk();
        $this->assertEmpty($response->json('data.system'));
    }

    public function test_loads_stations_via_system_when_requested(): void
    {
        $system = System::factory()->create();
        $body = SystemBody::factory()->create(['system_id' => $system->id]);
        $station = SystemStation::factory()->create(['system_id' => $system->id]);

        $response = $this->getJson("/api/bodies/{$body->slug}?withSystem=1&withStations=1");

        $response->assertOk();
        $response->assertJsonPath('data.system.name', $system->name);
        $stationNames = collect($response->json('data.system.stations'))->pluck('name')->all();
        $this->assertContains($station->name, $stationNames);
    }

    public function test_loads_sibling_bodies_via_system_when_requested(): void
    {
        $system = System::factory()->create();
        $body = SystemBody::factory()->create(['system_id' => $system->id]);
        $sibling = SystemBody::factory()->create(['system_id' => $system->id]);

        $response = $this->getJson("/api/bodies/{$body->slug}?withSystem=1&withBodies=1");

        $response->assertOk();
        $bodyNames = collect($response->json('data.system.bodies'))->pluck('name')->all();
        $this->assertContains($sibling->name, $bodyNames);
        $this->assertContains($body->name, $bodyNames);
    }

    public function test_returns_correct_body_data(): void
    {
        $body = SystemBody::factory()->create([
            'type' => 'Planet',
            'sub_type' => 'Earth-like world',
            'is_landable' => true,
            'gravity' => 1.0,
        ]);

        $response = $this->getJson("/api/bodies/{$body->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.type', 'Planet');
        $response->assertJsonPath('data.sub_type', 'Earth-like world');
        $response->assertJsonPath('data.is_landable', 1);
        $this->assertEquals(1.0, $response->json('data.gravity'));
    }

    public function test_soft_deleted_body_is_not_found(): void
    {
        $body = SystemBody::factory()->create();
        $body->delete();

        $response = $this->getJson("/api/bodies/{$body->slug}");

        $response->assertNotFound();
    }
}
