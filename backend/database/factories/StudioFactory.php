<?php

namespace Database\Factories;

use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Studio>
 */
class StudioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'street' => fake()->streetAddress(),
            'zip' => fake()->postcode(),
            'city' => fake()->city(),
            'description' => null,
            'thumbnail_url' => null,
            'image_url' => null,
            'web_link' => null,
        ];
    }
}
