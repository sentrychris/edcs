<?php

namespace Database\Factories;

use App\Models\System;
use App\Models\SystemStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemStation>
 */
class SystemStationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'system_id' => System::factory(),
            'market_id' => $this->faker->unique()->numberBetween(100000, 999999),
            'type' => $this->faker->randomElement(['Coriolis Starport', 'Orbis Starport', 'Outpost', 'Planetary Port']),
            'name' => $this->faker->unique()->company().' Station',
            'distance_to_arrival' => $this->faker->numberBetween(0, 50000),
            'allegiance' => $this->faker->randomElement(['Federation', 'Empire', 'Alliance', 'Independent']),
            'government' => $this->faker->randomElement(['Democracy', 'Corporate', 'Patronage']),
            'economy' => $this->faker->randomElement(['Industrial', 'Agricultural', 'High Tech']),
            'second_economy' => $this->faker->randomElement(['Extraction', 'Tourism', null]),
            'has_market' => $this->faker->boolean(80),
            'has_shipyard' => $this->faker->boolean(50),
            'has_outfitting' => $this->faker->boolean(60),
            'controlling_faction' => $this->faker->company(),
        ];
    }
}
