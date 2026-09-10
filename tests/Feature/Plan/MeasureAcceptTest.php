<?php

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('an administrator can accept work when all stages are approved', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $measure = Measure::factory()->create(['percent' => 78]);
    $period = Period::factory()->create(['month' => '2026-09-01']);

    $stages = [
        MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 30]),
        MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 70]),
        MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 3, 'weight' => 95]),
    ];

    foreach ([30, 70, 95] as $index => $approvedPercent) {
        StagePeriodUpdate::factory()->create([
            'measure_stage_id' => $stages[$index]->id,
            'period_id' => $period->id,
            'review_state' => ReviewState::Approved,
            'approved_percent' => $approvedPercent,
            'approved_by' => $administrator->id,
            'approved_at' => now()->subDay(),
        ]);
    }

    MeasurePeriodState::factory()->create([
        'measure_id' => $measure->id,
        'period_id' => $period->id,
        'status' => MeasureStatus::Overdue,
    ]);

    $this->actingAs($administrator)
        ->post(route('plan.measures.accept', $measure))
        ->assertRedirect();

    $measure->refresh();

    expect($measure->percent)->toBe(100)
        ->and($measure->latestPeriodState?->status)->toBe(MeasureStatus::Done);

    foreach ($stages as $stage) {
        $lastApproved = $stage->periodUpdates()
            ->where('review_state', ReviewState::Approved)
            ->orderByDesc('approved_at')
            ->first();

        expect($lastApproved?->approved_percent)->toBe(100);
    }
});

test('an observer cannot accept work', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $measure = Measure::factory()->create(['percent' => 50]);
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'weight' => 95]);
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => Period::factory(),
        'review_state' => ReviewState::Approved,
        'approved_percent' => 50,
        'approved_at' => now(),
    ]);

    $this->actingAs($observer)
        ->post(route('plan.measures.accept', $measure))
        ->assertForbidden();

    expect($measure->fresh()->percent)->toBe(50);
});

test('accept is rejected when not all stages are approved', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $measure = Measure::factory()->create(['percent' => 40]);
    $approved = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 40]);
    MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 60]);

    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $approved->id,
        'period_id' => Period::factory(),
        'review_state' => ReviewState::Approved,
        'approved_percent' => 100,
        'approved_at' => now(),
    ]);

    $this->actingAs($administrator)
        ->from(route('plan.index'))
        ->post(route('plan.measures.accept', $measure))
        ->assertRedirect(route('plan.index'))
        ->assertSessionHasErrors('measure');

    expect($measure->fresh()->percent)->toBe(40);
});
