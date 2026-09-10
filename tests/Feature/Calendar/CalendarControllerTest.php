<?php

use App\Models\Measure;
use App\Models\MeasureStage;
use App\Models\User;
use Database\Seeders\CalendarFocusSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CalendarFocusSeeder::class);
    $this->user = User::factory()->create();
});

test('all 10 calendar months are rendered with their focus and review body', function () {
    $this->actingAs($this->user)
        ->get(route('calendar.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('months', 10)
            ->where('months.0.month', '2026-09')
            ->where('months.0.review_body', 'РГ, МС'));
});

test('a measure with a deadline in a given month is linked to that month, not another', function () {
    $measure = Measure::factory()->create(['deadline' => '2026-10-15']);

    $this->actingAs($this->user)
        ->get(route('calendar.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('months', function ($months) use ($measure) {
                $october = collect($months)->firstWhere('month', '2026-10');
                $september = collect($months)->firstWhere('month', '2026-09');

                return collect($october['measures'])->pluck('id')->contains($measure->id)
                    && ! collect($september['measures'])->pluck('id')->contains($measure->id);
            }));
});

test('a measure is linked by a stage planned date even without a matching deadline', function () {
    $measure = Measure::factory()->create(['deadline' => '2027-06-20']);
    MeasureStage::factory()->create(['measure_id' => $measure->id, 'planned_date' => '2026-11-05']);

    $this->actingAs($this->user)
        ->get(route('calendar.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('months', function ($months) use ($measure) {
                $november = collect($months)->firstWhere('month', '2026-11');
                $june = collect($months)->firstWhere('month', '2027-06');

                return collect($november['measures'])->pluck('id')->contains($measure->id)
                    && collect($june['measures'])->pluck('id')->contains($measure->id);
            }));
});

test('a measure guard session cannot reach the calendar', function () {
    $this->get(route('calendar.index'))->assertRedirect(route('login'));
});
