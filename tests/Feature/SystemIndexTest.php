<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemInformation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_list_of_systems(): void
    {
        System::factory()->count(3)->create();

        $response = $this->getJson('/api/systems');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'id64', 'name', 'coords', 'slug', 'updated_at'],
            ],
        ]);
    }

    public function test_searches_systems_by_name(): void
    {
        System::factory()->create(['name' => 'Sol']);
        System::factory()->create(['name' => 'Alpha Centauri']);
        System::factory()->create(['name' => 'Solaris']);

        $response = $this->getJson('/api/systems?name=Sol');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Sol', $names);
        $this->assertContains('Solaris', $names);
        $this->assertNotContains('Alpha Centauri', $names);
    }

    public function test_exact_search_returns_only_exact_match(): void
    {
        System::factory()->create(['name' => 'Sol']);
        System::factory()->create(['name' => 'Solaris']);

        $response = $this->getJson('/api/systems?name=Sol&exactSearch=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Sol');
    }

    public function test_respects_limit_parameter(): void
    {
        System::factory()->count(5)->create();

        $response = $this->getJson('/api/systems?limit=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_validates_limit_maximum(): void
    {
        $response = $this->getJson('/api/systems?limit=999');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['limit']);
    }

    public function test_loads_information_relation_when_requested(): void
    {
        $system = System::factory()->create();
        SystemInformation::factory()->create(['system_id' => $system->id]);

        $response = $this->getJson('/api/systems?name='.$system->name.'&exactSearch=1&withInformation=1');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.0.information'));
    }

    public function test_does_not_load_relations_when_not_requested(): void
    {
        $system = System::factory()->create();
        SystemInformation::factory()->create(['system_id' => $system->id]);

        $response = $this->getJson('/api/systems?name='.$system->name.'&exactSearch=1');

        $response->assertOk();
        $this->assertEmpty($response->json('data.0.information'));
    }

    public function test_caches_results_when_no_name_given(): void
    {
        System::factory()->count(2)->create();

        $this->getJson('/api/systems')->assertOk();

        $this->assertNotNull(Cache::get('systems_page_1'));
    }

    public function test_returns_empty_data_when_no_systems_exist(): void
    {
        $response = $this->getJson('/api/systems');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}
