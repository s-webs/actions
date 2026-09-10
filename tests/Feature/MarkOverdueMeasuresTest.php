<?php

use App\Enums\MeasureStatus;
use App\Enums\RiskLevel;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\Period;
use App\Models\User;
use Database\Seeders\RoleSeeder;

test('a measure past its deadline with no prior status is marked overdue', function () {
    $measure = Measure::factory()->create(['deadline' => now()->subDay()]);

    $this->artisan('measures:mark-overdue')->assertSuccessful();

    $period = Period::current();
    $state = MeasurePeriodState::where('measure_id', $measure->id)->where('period_id', $period->id)->first();
    expect($state->status)->toBe(MeasureStatus::Overdue);
});

test('a done measure past its deadline is left alone', function () {
    $measure = Measure::factory()->create(['deadline' => now()->subDay(), 'percent' => 100]);
    $period = Period::current();
    MeasurePeriodState::factory()->create(['measure_id' => $measure->id, 'period_id' => $period->id, 'status' => MeasureStatus::Done]);

    $this->artisan('measures:mark-overdue');

    expect(MeasurePeriodState::where('measure_id', $measure->id)->where('period_id', $period->id)->first()->status)
        ->toBe(MeasureStatus::Done);
});

test('a measure not yet due is left alone', function () {
    $measure = Measure::factory()->create(['deadline' => now()->addMonth()]);

    $this->artisan('measures:mark-overdue');

    expect(MeasurePeriodState::where('measure_id', $measure->id)->exists())->toBeFalse();
});

test('a measure due in 10 days is persisted as at risk with medium risk', function () {
    $measure = Measure::factory()->create(['deadline' => now()->addDays(10), 'percent' => 0]);

    $this->artisan('measures:mark-overdue')->assertSuccessful();

    $period = Period::current();
    $state = MeasurePeriodState::where('measure_id', $measure->id)->where('period_id', $period->id)->first();

    expect($state->status)->toBe(MeasureStatus::AtRisk)
        ->and($measure->fresh()->risk_level)->toBe(RiskLevel::Medium);
});

test('marking overdue makes the plan registry SQL filter see it too, not just the live display', function () {
    $this->seed(RoleSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $measure = Measure::factory()->create(['deadline' => now()->subDay()]);

    $this->artisan('measures:mark-overdue');

    $this->actingAs($administrator)
        ->get(route('plan.index', ['status' => 'overdue']))
        ->assertInertia(fn ($page) => $page->where('measures.data.0.number', $measure->number));
});
