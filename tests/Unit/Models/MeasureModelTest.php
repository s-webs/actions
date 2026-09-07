<?php

use App\Enums\EvidenceType;
use App\Enums\PeriodState;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use App\Models\Direction;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;

test('measure belongs to a direction and casts risk level to an enum', function () {
    $direction = Direction::factory()->create(['name' => 'Академическая честность']);
    $measure = Measure::factory()->create([
        'direction_id' => $direction->id,
        'risk_level' => RiskLevel::High,
    ]);

    expect($measure->direction)->toBeInstanceOf(Direction::class)
        ->and($measure->direction->name)->toBe('Академическая честность')
        ->and($measure->risk_level)->toBe(RiskLevel::High);
});

test('measure stages sum weights and relate back to their measure', function () {
    $measure = Measure::factory()->create();
    MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 40]);
    MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 60]);

    expect($measure->stages)->toHaveCount(2)
        ->and($measure->stages->sum('weight'))->toBe(100)
        ->and($measure->stages->first()->measure->is($measure))->toBeTrue();
});

test('stage period update casts review state and links stage to period', function () {
    $stage = MeasureStage::factory()->create();
    $period = Period::factory()->create(['state' => PeriodState::Open]);

    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);

    expect($update->review_state)->toBe(ReviewState::Submitted)
        ->and($update->stage->is($stage))->toBeTrue()
        ->and($update->period->is($period))->toBeTrue()
        ->and($period->stageUpdates->first()->is($update))->toBeTrue();
});

test('evidence attaches to a measure and casts its type', function () {
    $measure = Measure::factory()->create();
    $evidence = Evidence::factory()->create([
        'measure_id' => $measure->id,
        'type' => EvidenceType::File,
        'path_or_url' => 'evidence/order.pdf',
    ]);

    expect($measure->evidences->first()->is($evidence))->toBeTrue()
        ->and($evidence->type)->toBe(EvidenceType::File);
});

test('measure credential hides its password hash from serialization', function () {
    $measure = Measure::factory()->create();
    $credential = MeasureCredential::factory()->create([
        'measure_id' => $measure->id,
        'login' => 'M-99',
    ]);

    expect($measure->credential->is($credential))->toBeTrue()
        ->and($credential->toArray())->not->toHaveKey('password_hash');
});
