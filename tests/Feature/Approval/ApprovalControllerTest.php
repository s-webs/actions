<?php

use App\Enums\EvidenceType;
use App\Enums\ReviewState;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
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
    $stage = MeasureStage::factory()->create($stageAttrs);
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
        ->post(route('approval.approve', $update))
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
            ->where('updates.0.needs_decision', true)
            ->where('updates.0.stage.is_last_stage', true));
});

test('approving a stage writes that stage percent onto the measure', function () {
    $measure = Measure::factory()->create();
    $stageA = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 30]);
    $stageB = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 70]);
    $stageC = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 3, 'weight' => 95]);
    $period = Period::factory()->create();

    $updateA = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stageA->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);

    $this->actingAs($this->administrator)
        ->post(route('approval.approve', $updateA))
        ->assertRedirect();

    expect($updateA->fresh()->review_state)->toBe(ReviewState::Approved)
        ->and($updateA->fresh()->approved_percent)->toBe(30)
        ->and($updateA->fresh()->approved_by)->toBe($this->administrator->id)
        ->and($measure->fresh()->percent)->toBe(30);

    $updateB = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stageB->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);
    $this->actingAs($this->administrator)->post(route('approval.approve', $updateB));

    expect($updateB->fresh()->approved_percent)->toBe(70)
        ->and($measure->fresh()->percent)->toBe(70);

    $updateC = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stageC->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);

    $this->actingAs($this->administrator)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page->where('updates.0.stage.is_last_stage', true));

    $this->actingAs($this->administrator)->post(route('approval.approve', $updateC));

    expect($updateC->fresh()->approved_percent)->toBe(100)
        ->and($measure->fresh()->percent)->toBe(100);
});

test('a rejected or reworked stage keeps its last approved percent sticky', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 60]);
    MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 95]);
    $period1 = Period::factory()->create(['month' => '2026-09-01']);
    $period2 = Period::factory()->create(['month' => '2026-10-01']);

    $firstUpdate = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period1->id,
        'review_state' => ReviewState::Submitted,
    ]);
    $this->actingAs($this->administrator)->post(route('approval.approve', $firstUpdate));
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

test('the missing-comment error is a real Russian message, not a raw translation key', function () {
    $update = submittedUpdate();

    $this->actingAs($this->administrator)->post(route('approval.rework', $update));

    expect(session('errors')->get('review_comment')[0])
        ->not->toBe('validation.required')
        ->and(session('errors')->get('review_comment')[0])->toContain('обязательно');
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

    $this->actingAs($this->administrator)->post(route('approval.approve', $update));

    $this->actingAs($this->administrator)
        ->post(route('approval.approve', $update))
        ->assertSessionHasErrors();
});

test('the queue exposes stored files as public storage urls, not relative paths', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $period = Period::factory()->create();
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);
    Evidence::factory()->create([
        'measure_id' => $measure->id,
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'type' => EvidenceType::File,
        'path_or_url' => 'evidence/52/report.xlsx',
        'title' => 'Отчёт',
    ]);

    $this->actingAs($this->administrator)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('updates.0.evidences.0.path_or_url', Storage::disk('public')->url('evidence/52/report.xlsx'))
            ->where('updates.0.evidences.0.title', 'Отчёт'));
});

test('the queue prioritises an overdue stage over a merely high-risk measure', function () {
    $overdueStage = submittedUpdate(['planned_date' => now()->subDay()]);
    $highRiskMeasure = Measure::factory()->create(['deadline' => now()->addMonths(6)]);
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
