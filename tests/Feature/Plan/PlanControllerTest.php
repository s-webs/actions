<?php

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\Responsible;
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

test('an administrator receives the import permission on the plan page', function () {
    $this->actingAs($this->administrator)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canImport', true)
            ->where('canCreate', true));
});

test('an observer does not receive the import permission on the plan page', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $this->actingAs($observer)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canImport', false)
            ->where('canCreate', false));
});

test('a measure with no period state shows as not started by default', function () {
    Measure::factory()->create(['number' => 1, 'deadline' => now()->addMonths(6)]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('plan/index')
            ->where('measures.data.0.status', 'not_started'));
});

test('the status filter uses the computed status', function () {
    Measure::factory()->create(['number' => 1, 'percent' => 100, 'deadline' => now()->addMonths(6)]);
    Measure::factory()->create(['number' => 2, 'percent' => 0, 'deadline' => now()->addDays(10)]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['status' => MeasureStatus::Done->value]))
        ->assertInertia(fn (Assert $page) => $page->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 1));

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['status' => MeasureStatus::AtRisk->value]))
        ->assertInertia(fn (Assert $page) => $page->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 2));
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

test('the risk level filter uses the computed risk', function () {
    Measure::factory()->create(['number' => 1, 'deadline' => now()->addDays(5)]);
    Measure::factory()->create(['number' => 2, 'deadline' => now()->addDays(10)]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['risk_level' => RiskLevel::High->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 1));
});

test('the search filter matches a measure by title', function () {
    Measure::factory()->create(['number' => 1, 'title' => 'Внедрение системы антиплагиата']);
    Measure::factory()->create(['number' => 2, 'title' => 'Обновление сайта']);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['search' => 'антиплагиата']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 1)
            ->where('filters.search', 'антиплагиата'));
});

test('the search filter matches a measure by number', function () {
    Measure::factory()->create(['number' => 12, 'title' => 'Первое мероприятие']);
    Measure::factory()->create(['number' => 3, 'title' => 'Второе мероприятие']);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['search' => '12']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 12));
});

test('the responsible name filter narrows the registry', function () {
    $matching = Responsible::factory()->create(['name' => 'Проректор по АР']);
    $other = Responsible::factory()->create(['name' => 'Декан']);

    Measure::factory()->create(['number' => 1, 'responsible_id' => $matching->id]);
    Measure::factory()->create(['number' => 2, 'responsible_id' => $other->id]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index', ['responsible' => 'Проректор']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data', fn ($data) => count($data) === 1)
            ->where('measures.data.0.number', 1));
});

test('a stage with a submitted report and document exposes details for the registry card', function () {
    $measure = Measure::factory()->create(['number' => 1]);
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'title' => 'Подготовка']);
    $period = Period::factory()->create();
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
        'done_text' => 'Собраны данные',
        'submitted_by_name' => 'Иванова А.С.',
    ]);
    $evidence = Evidence::factory()->create([
        'measure_id' => $measure->id,
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'title' => 'Протокол',
        'path_or_url' => 'https://example.test/protocol.pdf',
    ]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data.0.stages.0.has_details', true)
            ->where('measures.data.0.stages.0.update.done_text', 'Собраны данные')
            ->where('measures.data.0.stages.0.update.submitted_by_name', 'Иванова А.С.')
            ->where('measures.data.0.stages.0.evidences.0.id', $evidence->id)
            ->where('measures.data.0.stages.0.evidences.0.title', 'Протокол'));
});

test('an empty draft does not expose details', function () {
    $measure = Measure::factory()->create(['number' => 1]);
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => Period::factory(),
        'review_state' => ReviewState::Draft,
        'done_text' => null,
    ]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data.0.stages.0.has_details', false));
});

test('a stage without a report does not expose details', function () {
    $measure = Measure::factory()->create(['number' => 1]);
    MeasureStage::factory()->create(['measure_id' => $measure->id]);

    $this->actingAs($this->administrator)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data.0.stages.0.has_details', false)
            ->where('measures.data.0.stages.0.update', null));
});
