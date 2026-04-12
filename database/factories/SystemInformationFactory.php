<?php

namespace Database\Factories;

use App\Models\System;
use App\Models\SystemInformation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemInformation>
 */
class SystemInformationFactory extends Factory
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
            'allegiance' => $this->faker->randomElement(['Federation', 'Empire', 'Alliance', 'Independent']),
            'government' => $this->faker->randomElement(['Democracy', 'Corporate', 'Patronage', 'Dictatorship']),
            'faction' => $this->faker->company(),
            'faction_state' => $this->faker->randomElement(['Boom', 'Bust', 'War', 'Civil War', 'None']),
            'population' => $this->faker->numberBetween(0, 20000000000),
            'security' => $this->faker->randomElement(['$GAlAXY_MAP_INFO_state_high;', '$GAlAXY_MAP_INFO_state_medium;', '$GAlAXY_MAP_INFO_state_low;']),
            'economy' => $this->faker->randomElement(['Industrial', 'Agricultural', 'Extraction', 'High Tech', 'Tourism']),
        ];
    }
}
