<?php

namespace Database\Factories;

use App\Models\Measure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MeasureSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'measure_id' => Measure::factory(),
            'session_id' => Str::random(40),
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'started_at' => now(),
        ];
    }
}
