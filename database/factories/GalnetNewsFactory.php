<?php

namespace Database\Factories;

use App\Models\GalnetNews;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalnetNews>
 */
class GalnetNewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->unique()->sentence(5),
            'content' => $this->faker->paragraphs(3, true),
            'audio_file' => $this->faker->url(),
            'order_added' => $this->faker->unique()->numberBetween(1, 999999),
            'uploaded_at' => $this->faker->date('d M Y'),
            'banner_image' => $this->faker->url(),
        ];
    }
}
