<?php

namespace Tests\Feature;

use App\Models\FrontierUser;
use App\Models\User;
use App\Services\Frontier\FrontierAuthService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use ReflectionProperty;
use Tests\TestCase;

class FrontierAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(?Client $client = null): FrontierAuthService
    {
        $service = new FrontierAuthService;
        if ($client) {
            $property = new ReflectionProperty($service, 'client');
            $property->setValue($service, $client);
        }

        return $service;
    }

    private function mockGuzzle(array $responseBody): Client
    {
        $handler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseBody)),
        ]);

        return new Client(['handler' => HandlerStack::create($handler)]);
    }

    private function fakeFrontierProfile(string $customerId): object
    {
        return (object) ['usr' => (object) ['customer_id' => $customerId]];
    }

    private function fakeAuth(array $overrides = []): object
    {
        return (object) array_merge([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_type' => 'Bearer',
            'expires_in' => 14400,
        ], $overrides);
    }

    public function test_confirm_user_creates_new_user_with_all_token_fields(): void
    {
        $service = $this->makeService();
        $profile = $this->fakeFrontierProfile('123456');
        $auth = $this->fakeAuth();

        $user = $service->confirmUser($profile, $auth);

        $this->assertDatabaseHas('frontier_users', [
            'frontier_id' => '123456',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
        ]);
        $this->assertNotNull($user->fresh(['frontierUser'])->frontierUser->token_expires_at);
    }

    public function test_confirm_user_updates_existing_user_tokens(): void
    {
        $user = User::factory()->create(['email' => '123456@versyx.net']);
        FrontierUser::factory()->create([
            'user_id' => $user->id,
            'frontier_id' => '123456',
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
        ]);

        $service = $this->makeService();
        $profile = $this->fakeFrontierProfile('123456');
        $auth = $this->fakeAuth([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
        ]);

        $service->confirmUser($profile, $auth);

        $this->assertDatabaseHas('frontier_users', [
            'frontier_id' => '123456',
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
        ]);
        $this->assertDatabaseMissing('frontier_users', [
            'access_token' => 'old-access-token',
        ]);
    }

    public function test_confirm_user_caches_token_in_redis(): void
    {
        $service = $this->makeService();
        $profile = $this->fakeFrontierProfile('789');
        $auth = $this->fakeAuth(['access_token' => 'cached-token']);

        $user = $service->confirmUser($profile, $auth);

        $this->assertEquals('cached-token', Redis::get("user_{$user->id}_frontier_token"));
    }

    public function test_refresh_token_updates_tokens_in_database(): void
    {
        $user = User::factory()->create();
        $frontierUser = FrontierUser::factory()->expired()->create([
            'user_id' => $user->id,
            'refresh_token' => 'old-refresh-token',
        ]);

        $service = $this->makeService($this->mockGuzzle([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 14400,
        ]));

        $result = $service->refreshToken($user->fresh(['frontierUser']));

        $this->assertEquals('new-access-token', $result);
        $this->assertDatabaseHas('frontier_users', [
            'id' => $frontierUser->id,
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
        ]);
    }

    public function test_refresh_token_caches_new_token_in_redis(): void
    {
        $user = User::factory()->create();
        FrontierUser::factory()->expired()->create(['user_id' => $user->id]);

        $service = $this->makeService($this->mockGuzzle([
            'access_token' => 'fresh-token',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 14400,
        ]));

        $service->refreshToken($user->fresh(['frontierUser']));

        $this->assertEquals('fresh-token', Redis::get("user_{$user->id}_frontier_token"));
    }
}
