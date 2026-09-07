<?php

namespace Database\Factories;

use App\Models\Measure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MeasureCredentialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'measure_id' => Measure::factory(),
            'login' => 'M-'.$this->faker->unique()->numberBetween(1, 51),
            'password_hash' => Hash::make(Str::random(10)),
        ];
    }
}
