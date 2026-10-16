<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'name' => 'Saal '.fake()->unique()->numberBetween(1, 99),
            'capacity' => fake()->numberBetween(5, 50),
            'description' => null,
            'thumbnail_url' => null,
            'image_url' => null,
            'color_code' => null,
            'location_type' => 'indoor',
            'lighting' => null,
            'size_sqm' => null,
            'width_meters' => null,
            'length_meters' => null,
            'height_meters' => null,
        ];
    }
}
