<?php

namespace Tests\Feature;

use App\Exceptions\FrontierReauthorizationRequiredException;
use App\Models\User;
use App\Services\Frontier\FrontierCApiService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommanderDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/downloads/commander')->assertUnauthorized();
    }

    public function test_downloads_commander_profile_as_json(): void
    {
        $user = User::factory()->create();
        $profile = (object) ['commander' => (object) ['name' => 'TestCmdr', 'credits' => 1000000]];

        $this->mock(FrontierCApiService::class, function ($mock) use ($user, $profile) {
            $mock->shouldReceive('confirmCommander')->with(\Mockery::on(fn ($u) => $u->id === $user->id))->andReturn($profile);
        });

        $response = $this->actingAs($user, 'sanctum')->get('/api/downloads/commander');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('commander-profile.json', $response->headers->get('Content-Disposition'));
    }

    public function test_returns_401_when_frontier_reauthorization_required(): void
    {
        $user = User::factory()->create();

        $this->mock(FrontierCApiService::class, function ($mock) {
            $mock->shouldReceive('confirmCommander')->andThrow(new FrontierReauthorizationRequiredException);
        });

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/downloads/commander')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Frontier session expired. Please re-authenticate.');
    }

    public function test_returns_503_when_capi_throws(): void
    {
        $user = User::factory()->create();

        $this->mock(FrontierCApiService::class, function ($mock) {
            $mock->shouldReceive('confirmCommander')->andThrow(new Exception('CAPI error'));
        });

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/downloads/commander')
            ->assertServiceUnavailable();
    }

    public function test_returns_503_when_capi_returns_null(): void
    {
        $user = User::factory()->create();

        $this->mock(FrontierCApiService::class, function ($mock) {
            $mock->shouldReceive('confirmCommander')->andReturn(null);
        });

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/downloads/commander')
            ->assertServiceUnavailable();
    }
}
