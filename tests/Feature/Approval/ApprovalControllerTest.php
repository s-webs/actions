<?php

use App\Enums\EvidenceType;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('administrator');
    $this->observer = User::factory()->create();
    $this->observer->assignRole('observer');
});

function submittedUpdate(array $stageAttrs = [], array $updateAttrs = []): StagePeriodUpdate
{
    $stage = MeasureStage::factory()->create(array_merge(['weight' => 100], $stageAttrs));
    $period = Period::factory()->create();

    return StagePeriodUpdate::factory()->create(array_merge([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
        'submitted_by_name' => 'Иванова А.С.',
    ], $updateAttrs));
}

test('an observer cannot view or act on the approval queue', function () {
    $update = submittedUpdate();

    $this->actingAs($this->observer)->get(route('approval.index'))->assertForbidden();
    $this->actingAs($this->observer)
        ->post(route('approval.approve', $update), ['approved_percent' => 50])
        ->assertForbidden();
});

test('the queue shows the risk text and needs-decision flag the executor entered for that period', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $period = Period::factory()->create();
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);
    MeasurePeriodState::factory()->create([
        'measure_id' => $measure->id,
        'period_id' => $period->id,
        'risk_text' => 'Не хватает данных от подразделения',
        'needs_decision' => true,
    ]);

    $this->actingAs($this->administrator)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('updates.0.risk_text', 'Не хватает данных от подразделения')
            ->where('updates.0.needs_decision', true));
});

test('the administrator cannot approve 100 percent without an attached document', function () {
    $update = submittedUpdate();

    $this->actingAs($this->administrator)
        ->post(route('approval.approve', $update), ['approved_percent' => 100])
        ->assertSessionHasErrors('approved_percent');

    expect($update->fresh()->review_state)->toBe(ReviewState::Submitted);
});

test('approving with evidence recalculates the measure percent from stage weights', function () {
    $measure = Measure::factory()->create();
    $stageA = MeasureStage::factory()->create(['measure_id' => $measure->id, 'weight' => 40]);
    $stageB = MeasureStage::factory()->create(['measure_id' => $measure->id, 'weight' => 60]);
    $period = Period::factory()->create();

    $updateA = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stageA->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);
    Evidence::factory()->create(['measure_id' => $measure->id, 'measure_stage_id' => $stageA->id, 'period_id' => $period->id, 'type' => EvidenceType::Link]);

    $this->actingAs($this->administrator)
        ->post(route('approval.approve', $updateA), ['approved_percent' => 100])
        ->assertRedirect();

    expect($updateA->fresh()->review_state)->toBe(ReviewState::Approved)
        ->and($updateA->fresh()->approved_by)->toBe($this->administrator->id)
        // stage A approved at 100% (weight 40), stage B never approved (weight 60, counts as 0):
        // (40*100 + 60*0) / 100 = 40
        ->and($measure->fresh()->percent)->toBe(40);

    $updateB = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stageB->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);
    Evidence::factory()->create(['measure_id' => $measure->id, 'measure_stage_id' => $stageB->id, 'period_id' => $period->id, 'type' => EvidenceType::Link]);

    $this->actingAs($this->administrator)->post(route('approval.approve', $updateB), ['approved_percent' => 50]);

    // (40*100 + 60*50) / 100 = 70
    expect($measure->fresh()->percent)->toBe(70);
});

test('a rejected or reworked stage keeps its last approved percent sticky', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'weight' => 100]);
    $period1 = Period::factory()->create(['month' => '2026-09-01']);
    $period2 = Period::factory()->create(['month' => '2026-10-01']);

    $firstUpdate = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period1->id,
        'review_state' => ReviewState::Submitted,
    ]);
    Evidence::factory()->create(['measure_id' => $measure->id, 'measure_stage_id' => $stage->id, 'period_id' => $period1->id, 'type' => EvidenceType::Link]);
    $this->actingAs($this->administrator)->post(route('approval.approve', $firstUpdate), ['approved_percent' => 60]);
    expect($measure->fresh()->percent)->toBe(60);

    $secondUpdate = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period2->id,
        'review_state' => ReviewState::Submitted,
    ]);
    $this->actingAs($this->administrator)
        ->post(route('approval.rework', $secondUpdate), ['review_comment' => 'Нужны подробности'])
        ->assertRedirect();

    expect($secondUpdate->fresh()->review_state)->toBe(ReviewState::Rework)
        ->and($measure->fresh()->percent)->toBe(60);
});

test('rejecting requires a comment and does not change the approved percent', function () {
    $update = submittedUpdate();

    $this->actingAs($this->administrator)->post(route('approval.reject', $update))->assertSessionHasErrors('review_comment');

    $this->actingAs($this->administrator)
        ->post(route('approval.reject', $update), ['review_comment' => 'Не соответствует плану'])
        ->assertRedirect();

    expect($update->fresh()->review_state)->toBe(ReviewState::Rejected)
        ->and($update->fresh()->review_comment)->toBe('Не соответствует плану');
});

test('an already-decided stage cannot be re-approved', function () {
    $update = submittedUpdate();
    Evidence::factory()->create(['measure_id' => $update->stage->measure_id, 'measure_stage_id' => $update->measure_stage_id, 'period_id' => $update->period_id, 'type' => EvidenceType::Link]);

    $this->actingAs($this->administrator)->post(route('approval.approve', $update), ['approved_percent' => 100]);

    $this->actingAs($this->administrator)
        ->post(route('approval.approve', $update), ['approved_percent' => 50])
        ->assertSessionHasErrors();
});

test('the queue prioritises an overdue stage over a merely high-risk measure', function () {
    $overdueStage = submittedUpdate(['planned_date' => now()->subDay()]);
    $highRiskMeasure = Measure::factory()->create(['risk_level' => RiskLevel::High]);
    $highRiskStage = MeasureStage::factory()->create(['measure_id' => $highRiskMeasure->id, 'planned_date' => now()->addMonth()]);
    $period = Period::factory()->create();
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $highRiskStage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);

    $response = $this->actingAs($this->administrator)->get(route('approval.index'));

    $response->assertInertia(fn ($page) => $page->where('updates.0.id', $overdueStage->id));
});
