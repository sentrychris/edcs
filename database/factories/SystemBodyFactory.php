<?php

namespace Database\Factories;

use App\Models\System;
use App\Models\SystemBody;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemBody>
 */
class SystemBodyFactory extends Factory
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
            'id64' => $this->faker->unique()->numberBetween(100000000, 999999999),
            'body_id' => $this->faker->numberBetween(1, 100),
            'name' => $this->faker->unique()->word().' '.$this->faker->randomNumber(3),
            'discovered_by' => $this->faker->name(),
            'discovered_at' => $this->faker->dateTimeBetween('-5 years'),
            'type' => $this->faker->randomElement(['Star', 'Planet']),
            'sub_type' => $this->faker->randomElement(['Earth-like world', 'Water world', 'Gas giant', 'K (Yellow-Orange) Star']),
            'distance_to_arrival' => $this->faker->numberBetween(0, 50000),
            'is_main_star' => $this->faker->boolean(20),
            'is_scoopable' => $this->faker->boolean(),
            'spectral_class' => $this->faker->randomElement(['K', 'G', 'M', 'F', null]),
            'luminosity' => $this->faker->randomElement(['V', 'III', 'IV', null]),
            'solar_masses' => $this->faker->randomFloat(4, 0.01, 50),
            'solar_radius' => $this->faker->randomFloat(4, 0.01, 20),
            'absolute_magnitude' => $this->faker->randomFloat(4, -5, 15),
            'surface_temp' => $this->faker->numberBetween(20, 40000),
            'radius' => $this->faker->randomFloat(2, 1000, 80000),
            'gravity' => $this->faker->randomFloat(4, 0.01, 10),
            'earth_masses' => $this->faker->randomFloat(4, 0.001, 1000),
            'atmosphere_type' => $this->faker->randomElement(['No atmosphere', 'Suitable for water-based life', 'Thin', null]),
            'volcanism_type' => $this->faker->randomElement(['No volcanism', 'Minor', 'Major', null]),
            'terraforming_state' => $this->faker->randomElement(['Not terraformable', 'Terraformable', 'Terraforming', null]),
            'is_landable' => $this->faker->boolean(),
            'orbital_period' => $this->faker->randomFloat(4, 0.1, 10000),
            'orbital_eccentricity' => $this->faker->randomFloat(6, 0, 0.99),
            'orbital_inclination' => $this->faker->randomFloat(4, -180, 180),
            'arg_of_periapsis' => $this->faker->randomFloat(4, 0, 360),
            'rotational_period' => $this->faker->randomFloat(4, 0.1, 500),
            'is_tidally_locked' => $this->faker->boolean(),
            'semi_major_axis' => $this->faker->randomFloat(4, 0, 100),
            'axial_tilt' => $this->faker->randomFloat(4, -3.14, 3.14),
        ];
    }
}
