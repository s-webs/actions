<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResponsibleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->jobTitle(),
            'unit_id' => Unit::factory(),
        ];
    }
}
