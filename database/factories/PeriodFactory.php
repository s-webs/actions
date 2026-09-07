<?php

namespace Database\Factories;

use App\Enums\PeriodState;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'month' => $this->faker->unique()->dateTimeBetween('2026-09-01', '2027-06-01')->modify('first day of this month'),
            'state' => PeriodState::Open,
        ];
    }
}
