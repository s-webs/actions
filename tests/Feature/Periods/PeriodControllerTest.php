<?php

use App\Enums\MeasureStatus;
use App\Enums\PeriodState;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasurePeriodState;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\Snapshot;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole('coordinator');
    $this->proctor = User::factory()->create();
    $this->proctor->assignRole('proctor');
});

test('a proctor cannot close a period even though they can do most coordinator actions', function () {
    $this->actingAs($this->proctor)->get(route('periods.index'))->assertForbidden();
    $this->actingAs($this->proctor)->post(route('periods.close'))->assertForbidden();
});

test('closing the period snapshots every measure with its current percent, status, and risk level', function () {
    $measure = Measure::factory()->create(['percent' => 65, 'risk_level' => RiskLevel::Medium]);
    $period = Period::current();
    MeasurePeriodState::factory()->create(['measure_id' => $measure->id, 'period_id' => $period->id, 'status' => MeasureStatus::InProgress]);

    $this->actingAs($this->coordinator)->post(route('periods.close'))->assertRedirect();

    $snapshot = Snapshot::where('measure_id', $measure->id)->where('period_id', $period->id)->first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->percent)->toBe(65)
        ->and($snapshot->status)->toBe(MeasureStatus::InProgress)
        ->and($snapshot->risk_level)->toBe(RiskLevel::Medium);

    expect($period->fresh()->state)->toBe(PeriodState::Closed)
        ->and($period->fresh()->closed_by)->toBe($this->coordinator->id);
});

test('once a period is closed, the measure workspace rolls forward to the next month rather than reopening it', function () {
    $measure = Measure::factory()->create();
    $closingPeriod = Period::current();

    $this->actingAs($this->coordinator)->post(route('periods.close'));

    $nextPeriod = Period::current();
    expect($nextPeriod->id)->not->toBe($closingPeriod->id)
        ->and($nextPeriod->state)->toBe(PeriodState::Open)
        ->and($nextPeriod->month->isAfter($closingPeriod->month))->toBeTrue();

    // The measure workspace, visited after closing, writes into the new period - not the closed one.
    $credential = MeasureCredential::factory()->create(['measure_id' => $measure->id]);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    expect(StagePeriodUpdate::where('period_id', $closingPeriod->id)->exists())->toBeFalse();
});

test('stages still submitted or in rework at close time are reported as unreviewed before closing', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $period = Period::current();
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);

    $this->actingAs($this->coordinator)
        ->get(route('periods.index'))
        ->assertInertia(fn ($page) => $page
            ->where('unreviewedCount', 1)
            ->where('unreviewed.0.measure_number', $measure->number));
});
