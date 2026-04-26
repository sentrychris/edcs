<?php

namespace Tests\Feature;

use App\Models\System;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSystemByDistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_systems_within_range_using_coordinates(): void
    {
        $origin = System::factory()->atCoords(0, 0, 0)->create();
        $near = System::factory()->atCoords(10, 0, 0)->create();
        System::factory()->atCoords(500, 0, 0)->create();

        $response = $this->getJson('/api/systems/search/distance?x=0&y=0&z=0&ly=50');

        $response->assertOk();
        $response->assertJsonFragment(['name' => $origin->name]);
        $response->assertJsonFragment(['name' => $near->name]);
    }

    public function test_returns_systems_within_range_using_slug(): void
    {
        $origin = System::factory()->atCoords(0, 0, 0)->create();
        $near = System::factory()->atCoords(10, 0, 0)->create();
        System::factory()->atCoords(500, 0, 0)->create();

        $response = $this->getJson("/api/systems/search/distance?slug={$origin->slug}&ly=50");

        $response->assertOk();
        $response->assertJsonFragment(['name' => $origin->name]);
        $response->assertJsonFragment(['name' => $near->name]);
    }

    public function test_slug_and_coordinates_produce_identical_results(): void
    {
        $origin = System::factory()->atCoords(0, 0, 0)->create();
        System::factory()->atCoords(10, 0, 0)->create();

        $bySlug = $this->getJson("/api/systems/search/distance?slug={$origin->slug}&ly=50");
        $byCoords = $this->getJson('/api/systems/search/distance?x=0&y=0&z=0&ly=50');

        $bySlug->assertOk();
        $byCoords->assertOk();
        $this->assertEquals($bySlug->json('data'), $byCoords->json('data'));
    }

    public function test_excludes_systems_beyond_range(): void
    {
        System::factory()->atCoords(0, 0, 0)->create();
        $far = System::factory()->atCoords(200, 0, 0)->create();
        $outsideSphere = System::factory()->atCoords(40, 40, 0)->create();

        $response = $this->getJson('/api/systems/search/distance?x=0&y=0&z=0&ly=50');

        $response->assertOk();
        $response->assertJsonMissing(['name' => $far->name]);
        $response->assertJsonMissing(['name' => $outsideSphere->name]);
    }

    public function test_results_are_ordered_by_distance(): void
    {
        System::factory()->atCoords(0, 0, 0)->create();
        $farther = System::factory()->atCoords(20, 0, 0)->create();
        $nearer = System::factory()->atCoords(5, 0, 0)->create();

        $response = $this->getJson('/api/systems/search/distance?x=0&y=0&z=0&ly=50');

        $response->assertOk();
        $data = $response->json('data');
        $names = array_column($data, 'name');
        $this->assertSame($nearer->name, $names[1]);
        $this->assertSame($farther->name, $names[2]);
    }

    public function test_response_includes_distance_field(): void
    {
        System::factory()->atCoords(0, 0, 0)->create();
        System::factory()->atCoords(30, 0, 0)->create();

        $response = $this->getJson('/api/systems/search/distance?x=0&y=0&z=0&ly=50');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'id64', 'name', 'coords', 'distance', 'slug'],
            ],
        ]);
        $response->assertJsonPath('data.1.distance', 30);
    }

    public function test_validates_that_coordinates_are_required_without_slug(): void
    {
        $response = $this->getJson('/api/systems/search/distance?ly=50');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['x', 'y', 'z']);
    }

    public function test_validates_that_slug_must_exist(): void
    {
        $response = $this->getJson('/api/systems/search/distance?slug=nonexistent-slug&ly=50');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    }

    public function test_no_coordinate_errors_when_valid_slug_is_provided(): void
    {
        $system = System::factory()->atCoords(0, 0, 0)->create();

        $response = $this->getJson("/api/systems/search/distance?slug={$system->slug}&ly=50");

        $response->assertOk();
        $response->assertJsonMissingValidationErrors(['x', 'y', 'z']);
    }

    public function test_validates_ly_maximum(): void
    {
        $response = $this->getJson('/api/systems/search/distance?x=0&y=0&z=0&ly=9999');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['ly']);
    }

    public function test_validates_limit_maximum(): void
    {
        $response = $this->getJson('/api/systems/search/distance?x=0&y=0&z=0&ly=50&limit=9999');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['limit']);
    }
}
