<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_name' => $this->faker->word . '.jpg',
            'path' => $this->faker->uuid . '.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(1000, 1000000),
            'user_id' => null,
        ];
    }
}
