<?php

use App\Enums\MeasureStatus;
use App\Enums\PeriodState;
use App\Models\CalendarFocus;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Snapshot;
use App\Models\User;
use Database\Seeders\CalendarFocusSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CalendarFocusSeeder::class);
    $this->user = User::factory()->create();
    // Pick a calendar-focus month that is never the live current period, whatever
    // date the tests actually run on.
    $currentMonth = Period::current()->month->format('Y-m');
    $this->nonCurrentMonth = CalendarFocus::orderBy('month')->pluck('month')
        ->map(fn ($m) => $m->format('Y-m'))
        ->first(fn ($m) => $m !== $currentMonth);
});

test('the matrix has one column per calendar-focus month', function () {
    $this->actingAs($this->user)
        ->get(route('monitoring.index'))
        ->assertInertia(fn (Assert $page) => $page->where('months', fn ($m) => count($m) === CalendarFocus::count()));
});

test('a past, non-current month with no closed snapshot shows a dash', function () {
    Measure::factory()->create(['number' => 1]);

    $this->actingAs($this->user)
        ->get(route('monitoring.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells', function ($cells) {
                $cell = collect($cells)->firstWhere('month', $this->nonCurrentMonth);

                return $cell['symbol'] === '—' && $cell['status'] === null;
            }));
});

test('a closed period snapshot renders as its status symbol', function () {
    $measure = Measure::factory()->create(['number' => 1]);
    // A snapshot only ever exists for a closed period in real flow (task-016) - an open
    // period with a later month would otherwise make Period::current() treat it as the
    // live period instead of the one this test means to probe.
    $period = Period::factory()->create(['month' => $this->nonCurrentMonth.'-01', 'state' => PeriodState::Closed]);
    Snapshot::factory()->create([
        'measure_id' => $measure->id,
        'period_id' => $period->id,
        'status' => MeasureStatus::AtRisk,
        'percent' => 35,
    ]);

    $this->actingAs($this->user)
        ->get(route('monitoring.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells', function ($cells) {
                $cell = collect($cells)->firstWhere('month', $this->nonCurrentMonth);

                return $cell['symbol'] === '⚠' && $cell['percent'] === 35 && $cell['live'] === false;
            }));
});

test('the current unclosed month is computed live and flagged, not read from a snapshot', function () {
    Measure::factory()->create(['number' => 1, 'deadline' => now()->subDay()]);
    $currentMonth = Period::current()->month->format('Y-m');

    $this->actingAs($this->user)
        ->get(route('monitoring.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentMonth', $currentMonth)
            ->where('rows.0.cells', function ($cells) use ($currentMonth) {
                $current = collect($cells)->firstWhere('month', $currentMonth);

                return $current['live'] === true && $current['symbol'] === '!';
            }));
});
