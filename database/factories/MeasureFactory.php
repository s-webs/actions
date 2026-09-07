<?php

namespace Database\Factories;

use App\Enums\RiskLevel;
use App\Models\Direction;
use App\Models\Responsible;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeasureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1, 51),
            'direction_id' => Direction::factory(),
            'title' => $this->faker->sentence(8),
            'responsible_id' => Responsible::factory(),
            'deadline' => $this->faker->dateTimeBetween('now', '+9 months'),
            'interim_monitoring_text' => $this->faker->sentence(),
            'risk_level' => $this->faker->randomElement(RiskLevel::cases()),
            'percent' => 0,
        ];
    }
}
