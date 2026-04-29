<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_rejects_unauthenticated_request(): void
    {
        $this->getJson('/api/bookmarks')->assertUnauthorized();
    }

    public function test_store_rejects_unauthenticated_request(): void
    {
        $system = System::factory()->create();

        $this->postJson('/api/bookmarks', ['slug' => $system->slug])
            ->assertUnauthorized();
    }

    public function test_destroy_rejects_unauthenticated_request(): void
    {
        $system = System::factory()->create();

        $this->deleteJson("/api/bookmarks/{$system->slug}")->assertUnauthorized();
    }

    public function test_index_returns_empty_collection_for_user_with_no_bookmarks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookmarks');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_index_returns_only_authenticated_users_bookmarks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = System::factory()->create();
        $theirs = System::factory()->create();

        $user->bookmarkedSystems()->attach($mine);
        $other->bookmarkedSystems()->attach($theirs);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookmarks');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $mine->slug);
    }

    public function test_index_returns_most_recently_bookmarked_first(): void
    {
        $user = User::factory()->create();
        $first = System::factory()->create();
        $second = System::factory()->create();

        $user->bookmarkedSystems()->attach($first, ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $user->bookmarkedSystems()->attach($second, ['created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/bookmarks');

        $response->assertOk()
            ->assertJsonPath('data.0.slug', $second->slug)
            ->assertJsonPath('data.1.slug', $first->slug);
    }

    public function test_store_creates_bookmark(): void
    {
        $user = User::factory()->create();
        $system = System::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookmarks', ['slug' => $system->slug]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', $system->slug)
            ->assertJsonPath('data.is_bookmarked', true);

        $this->assertDatabaseHas('users_bookmarked_systems', [
            'user_id' => $user->id,
            'system_id' => $system->id,
        ]);
    }

    public function test_store_is_idempotent(): void
    {
        $user = User::factory()->create();
        $system = System::factory()->create();
        $user->bookmarkedSystems()->attach($system);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookmarks', ['slug' => $system->slug])
            ->assertCreated();

        $this->assertSame(1, $user->bookmarkedSystems()->count());
    }

    public function test_store_rejects_unknown_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookmarks', ['slug' => '999999-nope'])
            ->assertUnprocessable();
    }

    public function test_store_rejects_missing_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookmarks', [])
            ->assertUnprocessable();
    }

    public function test_destroy_removes_bookmark(): void
    {
        $user = User::factory()->create();
        $system = System::factory()->create();
        $user->bookmarkedSystems()->attach($system);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/bookmarks/{$system->slug}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users_bookmarked_systems', [
            'user_id' => $user->id,
            'system_id' => $system->id,
        ]);
    }

    public function test_destroy_is_idempotent_when_not_bookmarked(): void
    {
        $user = User::factory()->create();
        $system = System::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/bookmarks/{$system->slug}")
            ->assertNoContent();
    }

    public function test_destroy_does_not_affect_other_users_bookmarks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $system = System::factory()->create();
        $user->bookmarkedSystems()->attach($system);
        $other->bookmarkedSystems()->attach($system);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/bookmarks/{$system->slug}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users_bookmarked_systems', [
            'user_id' => $user->id,
            'system_id' => $system->id,
        ]);
        $this->assertDatabaseHas('users_bookmarked_systems', [
            'user_id' => $other->id,
            'system_id' => $system->id,
        ]);
    }

    public function test_destroy_returns_404_for_unknown_system(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/bookmarks/999999-nope')
            ->assertNotFound();
    }

    public function test_system_resource_exposes_is_bookmarked_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $bookmarked = System::factory()->create();
        $other = System::factory()->create();
        $user->bookmarkedSystems()->attach($bookmarked);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/systems/{$bookmarked->slug}")
            ->assertOk()
            ->assertJsonPath('data.is_bookmarked', true);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/systems/{$other->slug}")
            ->assertOk()
            ->assertJsonPath('data.is_bookmarked', false);
    }

    public function test_system_resource_omits_is_bookmarked_for_guest(): void
    {
        $system = System::factory()->create();

        $response = $this->getJson("/api/systems/{$system->slug}");

        $response->assertOk();
        $this->assertArrayNotHasKey('is_bookmarked', $response->json('data'));
    }
}
