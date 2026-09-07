<?php

use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('a proctor can approve a stage period update', function () {
    $proctor = User::factory()->create();
    $proctor->assignRole('proctor');

    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => MeasureStage::factory(),
        'period_id' => Period::factory(),
    ]);

    expect($proctor->can('approve', $update))->toBeTrue();
});

test('a coordinator cannot approve a stage period update', function () {
    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');

    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => MeasureStage::factory(),
        'period_id' => Period::factory(),
    ]);

    expect($coordinator->can('approve', $update))->toBeFalse();
});
