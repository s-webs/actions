<?php

namespace Database\Factories;

use App\Models\Measure;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeasureStageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'measure_id' => Measure::factory(),
            'order' => 1,
            'title' => $this->faker->sentence(3),
            'planned_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'weight' => 50,
        ];
    }
}
