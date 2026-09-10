<?php

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;

test('enum labels resolve to russian under ru locale', function () {
    app()->setLocale('ru');

    expect(ReviewState::Approved->label())->toBe('Утверждён')
        ->and(ReviewState::Submitted->label())->toBe('На проверке')
        ->and(MeasureStatus::Overdue->label())->toBe('Просрочено')
        ->and(RiskLevel::High->label())->toBe('Высокий');
});

test('enum labels resolve to english under en locale', function () {
    app()->setLocale('en');

    expect(ReviewState::Approved->label())->toBe('Approved')
        ->and(MeasureStatus::Done->label())->toBe('Done')
        ->and(RiskLevel::Low->label())->toBe('Low');
});
