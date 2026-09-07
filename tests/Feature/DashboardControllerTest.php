<?php

use App\Enums\MeasureStatus;
use App\Enums\RiskLevel;
use App\Models\Direction;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\Period;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function measureWithStatus(Direction $direction, MeasureStatus $status, array $attrs = []): Measure
{
    $measure = Measure::factory()->create(array_merge(['direction_id' => $direction->id], $attrs));
    $period = Period::factory()->create();
    MeasurePeriodState::factory()->create(['measure_id' => $measure->id, 'period_id' => $period->id, 'status' => $status]);

    return $measure;
}

test('kpis count measures by their current status', function () {
    $direction = Direction::factory()->create(['in_summary' => true]);
    measureWithStatus($direction, MeasureStatus::Done);
    measureWithStatus($direction, MeasureStatus::InProgress);
    measureWithStatus($direction, MeasureStatus::AtRisk);
    measureWithStatus($direction, MeasureStatus::NotStarted);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.total', 4)
            ->where('kpis.done', 1)
            ->where('kpis.in_progress', 1)
            ->where('kpis.at_risk', 1));
});

test('a measure past its deadline shows as overdue even without a persisted status', function () {
    Measure::factory()->create(['deadline' => now()->subDay()]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('kpis.overdue', 1));
});

test('a done measure past its deadline is not counted as overdue', function () {
    $direction = Direction::factory()->create();
    measureWithStatus($direction, MeasureStatus::Done, ['deadline' => now()->subDay()]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('kpis.overdue', 0)->where('kpis.done', 1));
});

test('direction summary only includes directions flagged for the dashboard', function () {
    $summaryDirection = Direction::factory()->create(['in_summary' => true, 'name' => 'В своде']);
    $hiddenDirection = Direction::factory()->create(['in_summary' => false, 'name' => 'Не в своде']);
    Measure::factory()->create(['direction_id' => $summaryDirection->id, 'percent' => 50]);
    Measure::factory()->create(['direction_id' => $hiddenDirection->id, 'percent' => 10]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('directionSummary', fn ($rows) => count($rows) === 1 && $rows[0]['name'] === 'В своде'));
});

test('needs-decision picks up high risk, at-risk status, needs_decision flag, and near deadlines independently', function () {
    $direction = Direction::factory()->create();

    $highRisk = Measure::factory()->create(['direction_id' => $direction->id, 'risk_level' => RiskLevel::High, 'deadline' => now()->addMonths(6)]);
    $atRisk = measureWithStatus($direction, MeasureStatus::AtRisk, ['deadline' => now()->addMonths(6)]);
    $nearDeadline = Measure::factory()->create(['direction_id' => $direction->id, 'deadline' => now()->addDays(10)]);
    $fine = Measure::factory()->create(['direction_id' => $direction->id, 'deadline' => now()->addMonths(6), 'risk_level' => RiskLevel::Low]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($highRisk, $atRisk, $nearDeadline, $fine) {
            $page->where('needsDecision', function ($rows) use ($highRisk, $atRisk, $nearDeadline, $fine) {
                $ids = collect($rows)->pluck('id');

                return $ids->contains($highRisk->id)
                    && $ids->contains($atRisk->id)
                    && $ids->contains($nearDeadline->id)
                    && ! $ids->contains($fine->id);
            });
        });
});

test('top risks ranks high risk above a merely near-deadline measure', function () {
    $direction = Direction::factory()->create();
    $highRisk = Measure::factory()->create([
        'direction_id' => $direction->id,
        'risk_level' => RiskLevel::High,
        'deadline' => now()->addMonths(3),
        'percent' => 80,
    ]);
    $lowRiskSoon = Measure::factory()->create([
        'direction_id' => $direction->id,
        'risk_level' => RiskLevel::Low,
        'deadline' => now()->addDays(5),
        'percent' => 10,
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('topRisks.0.id', $highRisk->id)->where('topRisks.1.id', $lowRiskSoon->id));
});

test('a measure guard session cannot reach the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
