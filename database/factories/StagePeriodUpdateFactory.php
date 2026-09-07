<?php

namespace Database\Factories;

use App\Enums\ReviewState;
use App\Models\MeasureStage;
use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

class StagePeriodUpdateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'measure_stage_id' => MeasureStage::factory(),
            'period_id' => Period::factory(),
            'done_text' => $this->faker->sentence(),
            'review_state' => ReviewState::Draft,
        ];
    }
}
