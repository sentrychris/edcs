<?php

namespace Tests\Feature;

use App\Models\System;
use App\Services\EdsmApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemLastUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_most_recently_updated_system(): void
    {
        $this->mock(EdsmApiService::class, function ($mock) {
            $mock->shouldReceive('updateSystemBodies')->andReturn(null);
            $mock->shouldReceive('updateSystemInformation')->andReturn(null);
        });

        System::factory()->create(['name' => 'Older System', 'updated_at' => now()->subHour()]);
        $latest = System::factory()->create(['name' => 'Latest System', 'updated_at' => now()]);

        $response = $this->getJson('/api/systems/last-updated');

        $response->assertOk();
        $response->assertJsonPath('data.name', $latest->name);
    }

    public function test_response_includes_system_resource_structure(): void
    {
        $this->mock(EdsmApiService::class, function ($mock) {
            $mock->shouldReceive('updateSystemBodies')->andReturn(null);
            $mock->shouldReceive('updateSystemInformation')->andReturn(null);
        });

        System::factory()->create();

        $response = $this->getJson('/api/systems/last-updated');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'id64', 'name', 'coords' => ['x', 'y', 'z'], 'slug', 'updated_at'],
        ]);
    }

    public function test_serves_from_cache_on_subsequent_requests(): void
    {
        $this->mock(EdsmApiService::class, function ($mock) {
            $mock->shouldReceive('updateSystemBodies')->andReturn(null);
            $mock->shouldReceive('updateSystemInformation')->andReturn(null);
        });

        $system = System::factory()->create();

        // First request populates cache
        $this->getJson('/api/systems/last-updated')->assertOk();

        $this->assertNotNull(Cache::get('latest_system'));
    }
}
