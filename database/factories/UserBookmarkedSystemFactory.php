<?php

namespace Database\Factories;

use App\Models\System;
use App\Models\User;
use App\Models\UserBookmarkedSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBookmarkedSystem>
 */
class UserBookmarkedSystemFactory extends Factory
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
            'system_id' => System::factory(),
        ];
    }
}
