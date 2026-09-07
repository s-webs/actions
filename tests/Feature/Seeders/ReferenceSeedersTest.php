<?php

use App\Models\CalendarFocus;
use App\Models\Direction;
use Database\Seeders\CalendarFocusSeeder;
use Database\Seeders\DirectionSeeder;

test('direction seeder loads all 28 directions with the documented summary flags', function () {
    $this->seed(DirectionSeeder::class);

    expect(Direction::count())->toBe(28)
        ->and(Direction::where('in_summary', true)->count())->toBe(13)
        ->and(Direction::firstWhere('number', 10)->name)->toBe('Академическая честность')
        ->and(Direction::firstWhere('number', 10)->in_summary)->toBeTrue()
        ->and(Direction::firstWhere('number', 1)->in_summary)->toBeFalse();

    // Idempotent: seeding twice must not duplicate rows.
    $this->seed(DirectionSeeder::class);
    expect(Direction::count())->toBe(28);
});

test('calendar focus seeder loads all 10 months of the monitoring calendar', function () {
    $this->seed(CalendarFocusSeeder::class);

    expect(CalendarFocus::count())->toBe(10);

    $september = CalendarFocus::whereDate('month', '2026-09-01')->first();
    expect($september->review_body)->toBe('РГ, МС')
        ->and($september->focus_text)->toContain('Запуск плана');

    $june = CalendarFocus::whereDate('month', '2027-06-01')->first();
    expect($june->review_body)->toBe('МС, УС, ректорат');

    // Idempotent: seeding twice must not duplicate or crash on the unique constraint.
    $this->seed(CalendarFocusSeeder::class);
    expect(CalendarFocus::count())->toBe(10);
});
