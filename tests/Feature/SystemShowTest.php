<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemInformation;
use App\Services\EdsmApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_system_by_slug(): void
    {
        $system = System::factory()->create();

        $response = $this->getJson("/api/systems/{$system->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.name', $system->name);
        $response->assertJsonPath('data.id64', $system->id64);
        $response->assertJsonPath('data.slug', $system->slug);
    }

    public function test_returns_404_when_system_not_found(): void
    {
        $this->mock(EdsmApiService::class, function ($mock) {
            $mock->shouldReceive('updateSystem')->once()->andReturn(false);
        });

        $response = $this->getJson('/api/systems/999999-nonexistent');

        $response->assertNotFound();
    }

    public function test_caches_system_after_first_request(): void
    {
        $system = System::factory()->create();

        $this->getJson("/api/systems/{$system->slug}")->assertOk();

        $this->assertNotNull(Cache::get("system_detail_{$system->slug}"));
    }

    public function test_serves_from_cache_on_subsequent_requests(): void
    {
        $system = System::factory()->create();

        // First request populates cache
        $this->getJson("/api/systems/{$system->slug}")->assertOk();

        // Second request should serve from cache (system can be deleted to prove it)
        $system->forceDelete();
        $response = $this->getJson("/api/systems/{$system->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.name', $system->name);
    }

    public function test_loads_information_relation_when_requested(): void
    {
        $system = System::factory()->create();
        SystemInformation::factory()->create(['system_id' => $system->id]);

        $this->mock(EdsmApiService::class);

        $response = $this->getJson("/api/systems/{$system->slug}?withInformation=1");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.information'));
    }

    public function test_does_not_load_relations_when_not_requested(): void
    {
        $system = System::factory()->create();
        SystemInformation::factory()->create(['system_id' => $system->id]);

        $response = $this->getJson("/api/systems/{$system->slug}");

        $response->assertOk();
        $this->assertEmpty($response->json('data.information'));
    }

    public function test_queries_edsm_when_system_not_in_database(): void
    {
        // Create and re-fetch to clear wasRecentlyCreated
        $system = System::factory()->create()->fresh();
        $slug = $system->slug;
        $name = $system->name;

        $this->mock(EdsmApiService::class, function ($mock) use ($system) {
            $mock->shouldReceive('updateSystem')
                ->once()
                ->andReturn($system);
        });

        // Force delete so the controller falls through to EDSM
        $system->forceDelete();

        $response = $this->getJson("/api/systems/{$slug}");

        $response->assertOk();
        $response->assertJsonPath('data.name', $name);
    }

    public function test_response_includes_correct_structure(): void
    {
        $system = System::factory()->create();

        $response = $this->getJson("/api/systems/{$system->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'id64', 'name', 'coords' => ['x', 'y', 'z'], 'slug', 'updated_at'],
        ]);
    }
}
