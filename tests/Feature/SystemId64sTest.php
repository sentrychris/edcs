<?php

namespace Tests\Feature;

use App\Models\System;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemId64sTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_all_system_id64s_as_json_array(): void
    {
        $systems = System::factory()->count(3)->create();
        $expectedId64s = $systems->pluck('id64')->sort()->values()->all();

        $response = $this->get('/api/systems/id64s');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $returnedId64s = collect(json_decode($response->streamedContent(), true))
            ->sort()
            ->values()
            ->all();

        $this->assertEquals($expectedId64s, $returnedId64s);
    }

    public function test_returns_empty_array_when_no_systems_exist(): void
    {
        $response = $this->get('/api/systems/id64s');

        $response->assertOk();
        $this->assertEquals([], json_decode($response->streamedContent(), true));
    }

    public function test_response_contains_only_id64_values(): void
    {
        $system = System::factory()->create();

        $response = $this->get('/api/systems/id64s');

        $response->assertOk();
        $data = json_decode($response->streamedContent(), true);

        $this->assertCount(1, $data);
        $this->assertEquals($system->id64, $data[0]);
    }
}
