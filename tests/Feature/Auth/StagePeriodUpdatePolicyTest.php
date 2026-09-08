<?php

use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('an administrator can approve a stage period update', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => MeasureStage::factory(),
        'period_id' => Period::factory(),
    ]);

    expect($administrator->can('approve', $update))->toBeTrue();
});

test('an observer cannot approve a stage period update', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => MeasureStage::factory(),
        'period_id' => Period::factory(),
    ]);

    expect($observer->can('approve', $update))->toBeFalse();
});

test('a developer can approve a stage period update without the administrator role', function () {
    $developer = User::factory()->create();
    $developer->assignRole('developer');

    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => MeasureStage::factory(),
        'period_id' => Period::factory(),
    ]);

    expect($developer->can('approve', $update))->toBeTrue();
});
