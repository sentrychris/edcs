<?php

namespace Database\Factories;

use App\Models\FrontierUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FrontierUser>
 */
class FrontierUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'frontier_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'token_expires_at' => now()->addHours(4),
        ];
    }

    /**
     * Indicate that the access token has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * Indicate that the refresh token is unavailable (user must re-authorize).
     */
    public function withoutRefreshToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'refresh_token' => null,
            'token_expires_at' => now()->subMinutes(10),
        ]);
    }
}
