<?php

namespace Database\Factories;

use App\Enums\EvidenceType;
use App\Models\Measure;
use Illuminate\Database\Eloquent\Factories\Factory;

class EvidenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'measure_id' => Measure::factory(),
            'type' => EvidenceType::Link,
            'path_or_url' => $this->faker->url(),
            'title' => $this->faker->sentence(3),
        ];
    }
}
