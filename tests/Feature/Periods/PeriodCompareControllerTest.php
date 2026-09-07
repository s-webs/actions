<?php

use App\Enums\MeasureStatus;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Snapshot;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');
});

test('comparing two periods flags a measure whose percent or status changed', function () {
    $measure = Measure::factory()->create();
    $periodA = Period::factory()->create(['month' => '2026-09-01']);
    $periodB = Period::factory()->create(['month' => '2026-10-01']);
    Snapshot::factory()->create(['measure_id' => $measure->id, 'period_id' => $periodA->id, 'percent' => 20, 'status' => MeasureStatus::InProgress]);
    Snapshot::factory()->create(['measure_id' => $measure->id, 'period_id' => $periodB->id, 'percent' => 60, 'status' => MeasureStatus::InProgress]);

    $this->actingAs($this->coordinator)
        ->get(route('periods.compare', ['from' => $periodA->id, 'to' => $periodB->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comparison.0.from_percent', 20)
            ->where('comparison.0.to_percent', 60)
            ->where('comparison.0.changed', true));
});

test('an unchanged measure is not flagged', function () {
    $measure = Measure::factory()->create();
    $periodA = Period::factory()->create(['month' => '2026-09-01']);
    $periodB = Period::factory()->create(['month' => '2026-10-01']);
    Snapshot::factory()->create(['measure_id' => $measure->id, 'period_id' => $periodA->id, 'percent' => 40, 'status' => MeasureStatus::InProgress]);
    Snapshot::factory()->create(['measure_id' => $measure->id, 'period_id' => $periodB->id, 'percent' => 40, 'status' => MeasureStatus::InProgress]);

    $this->actingAs($this->coordinator)
        ->get(route('periods.compare', ['from' => $periodA->id, 'to' => $periodB->id]))
        ->assertInertia(fn (Assert $page) => $page->where('comparison.0.changed', false));
});
