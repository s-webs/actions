<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DirectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1, 28),
            'name' => $this->faker->sentence(4),
            'in_summary' => $this->faker->boolean(),
        ];
    }
}
