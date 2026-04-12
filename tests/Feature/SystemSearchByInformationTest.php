<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\SystemInformation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSearchByInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_systems_by_allegiance(): void
    {
        $federation = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $federation->id,
            'allegiance' => 'Federation',
        ]);

        $empire = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $empire->id,
            'allegiance' => 'Empire',
        ]);

        $response = $this->getJson('/api/systems/search/information?allegiance=Federation');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($federation->name, $names);
        $this->assertNotContains($empire->name, $names);
    }

    public function test_filters_systems_by_government(): void
    {
        $democracy = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $democracy->id,
            'government' => 'Democracy',
        ]);

        $corporate = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $corporate->id,
            'government' => 'Corporate',
        ]);

        $response = $this->getJson('/api/systems/search/information?government=Democracy');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($democracy->name, $names);
        $this->assertNotContains($corporate->name, $names);
    }

    public function test_filters_systems_by_economy(): void
    {
        $industrial = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $industrial->id,
            'economy' => 'Industrial',
        ]);

        $agricultural = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $agricultural->id,
            'economy' => 'Agricultural',
        ]);

        $response = $this->getJson('/api/systems/search/information?economy=Industrial');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($industrial->name, $names);
        $this->assertNotContains($agricultural->name, $names);
    }

    public function test_filters_systems_by_security(): void
    {
        $high = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $high->id,
            'security' => 'High',
        ]);

        $low = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $low->id,
            'security' => 'Low',
        ]);

        $response = $this->getJson('/api/systems/search/information?security=High');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($high->name, $names);
        $this->assertNotContains($low->name, $names);
    }

    public function test_filters_systems_by_minimum_population(): void
    {
        $populated = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $populated->id,
            'population' => 5000000000,
        ]);

        $sparse = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $sparse->id,
            'population' => 1000,
        ]);

        $response = $this->getJson('/api/systems/search/information?population=1000000');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($populated->name, $names);
        $this->assertNotContains($sparse->name, $names);
    }

    public function test_combines_multiple_filters(): void
    {
        $match = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $match->id,
            'allegiance' => 'Federation',
            'government' => 'Democracy',
        ]);

        $noMatch = System::factory()->create();
        SystemInformation::factory()->create([
            'system_id' => $noMatch->id,
            'allegiance' => 'Federation',
            'government' => 'Corporate',
        ]);

        $response = $this->getJson('/api/systems/search/information?allegiance=Federation&government=Democracy');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains($match->name, $names);
        $this->assertNotContains($noMatch->name, $names);
    }

    public function test_returns_all_systems_when_no_filters_given(): void
    {
        System::factory()->count(3)->create();

        $response = $this->getJson('/api/systems/search/information');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_validates_population_is_non_negative(): void
    {
        $response = $this->getJson('/api/systems/search/information?population=-1');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['population']);
    }
}
