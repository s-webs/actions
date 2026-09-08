<?php

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;
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
});

test('a measure-guard session cannot reach the plan registry', function () {
    $this->get(route('plan.index'))->assertRedirect(route('login'));
});

test('a measure with no period state shows as not started by default', function () {
    Measure::factory()->create(['number' => 1]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan/index')
            ->where('measures.data.0.status', 'not_started'));
});

test('the status filter reflects the latest period, not an earlier one', function () {
    $measure = Measure::factory()->create(['number' => 1]);
    $earlier = Period::factory()->create(['month' => '2026-09-01']);
    $later = Period::factory()->create(['month' => '2026-10-01']);

    MeasurePeriodState::factory()->create([
        'measure_id' => $measure->id,
        'period_id' => $earlier->id,
        'status' => MeasureStatus::AtRisk,
    ]);
    MeasurePeriodState::factory()->create([
        'measure_id' => $measure->id,
        'period_id' => $later->id,
        'status' => MeasureStatus::Done,
    ]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['status' => MeasureStatus::Done->value]))
        ->assertInertia(fn (Assert $page) => $page->where('measures.data', fn ($data) => count($data) === 1));

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['status' => MeasureStatus::AtRisk->value]))
        ->assertInertia(fn (Assert $page) => $page->where('measures.data', fn ($data) => count($data) === 0));
});

test('the has_stages_to_review filter matches measures with a submitted stage', function () {
    $withReview = Measure::factory()->create(['number' => 1]);
    $withoutReview = Measure::factory()->create(['number' => 2]);

    $stage = MeasureStage::factory()->create(['measure_id' => $withReview->id]);
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => Period::factory(),
        'review_state' => ReviewState::Submitted,
    ]);
    MeasureStage::factory()->create(['measure_id' => $withoutReview->id]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['has_stages_to_review' => '1']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 1));
});

test('the risk level filter narrows the registry', function () {
    Measure::factory()->create(['number' => 1, 'risk_level' => RiskLevel::High]);
    Measure::factory()->create(['number' => 2, 'risk_level' => RiskLevel::Low]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['risk_level' => RiskLevel::High->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 1));
});
