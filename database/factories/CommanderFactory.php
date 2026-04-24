<?php

namespace Database\Factories;

use App\Models\Commander;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commander>
 */
class CommanderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userIds = User::all()->pluck('id')->toArray();

        return [
            'user_id' => $userIds[array_rand($userIds)],
            'cmdr_name' => ucfirst(fake()->firstName()).' '.ucfirst(fake()->lastName()),
            'inara_api_key' => bin2hex(random_bytes(20)),
            'edsm_api_key' => bin2hex(random_bytes(20)),
        ];
    }
}
