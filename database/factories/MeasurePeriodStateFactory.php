<?php

namespace Database\Factories;

use App\Enums\MeasureStatus;
use App\Models\Measure;
use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeasurePeriodStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'measure_id' => Measure::factory(),
            'period_id' => Period::factory(),
            'status' => MeasureStatus::NotStarted,
        ];
    }
}
