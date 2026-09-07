<?php

use App\Enums\RiskLevel;
use App\Models\Direction;
use App\Models\Measure;
use App\Services\MeasureImportService;
use Illuminate\Support\Facades\Hash;

/**
 * Использует настоящий исходный файл заказчика, а не синтетическую фикстуру — это
 * единственный способ проверить реальную неоднозначность меток направлений
 * («Качество» → 23 или 27) и смешанные форматы дат в одном листе.
 */
function fixturePath(): string
{
    return base_path('docs/План действий академ блок 2026-27 yukma_dashboard.xlsx');
}

test('importing the real Action Plan file creates 51 measures with generated credentials', function () {
    $report = app(MeasureImportService::class)->import(fixturePath());

    expect($report->accepted)->toBe(51)
        ->and($report->updated)->toBe(0)
        ->and(Measure::count())->toBe(51)
        ->and($report->credentials)->toHaveCount(51);

    $measure1 = Measure::firstWhere('number', 1);
    expect($measure1->title)->toContain('Утверждение плана')
        ->and($measure1->risk_level)->toBe(RiskLevel::High)
        ->and($measure1->deadline->format('Y-m-d'))->toBe('2026-09-05')
        ->and($measure1->control_date)->not->toBeNull()
        ->and($measure1->responsible->name)->toBe('Проректор по АР')
        ->and($measure1->credential->login)->toBe('M-01');

    expect(Hash::check($report->credentials[0]['password'], $measure1->fresh()->credential->password_hash))->toBeTrue();
});

test('the ambiguous "Качество" label resolves to the correct direction by measure content', function () {
    app(MeasureImportService::class)->import(fixturePath());

    $qualityMeasure = Measure::firstWhere('number', 44);
    $gradingMeasure = Measure::firstWhere('number', 45);

    expect($qualityMeasure->direction->number)->toBe(23)
        ->and($gradingMeasure->direction->number)->toBe(27)
        ->and($gradingMeasure->direction->name)->toContain('Оценивание');
});

test('re-importing the same file updates measures without duplicating them or regenerating credentials', function () {
    $service = app(MeasureImportService::class);

    $first = $service->import(fixturePath());
    $firstLogin = Measure::firstWhere('number', 1)->credential->login;
    $firstHash = Measure::firstWhere('number', 1)->credential->password_hash;

    $second = $service->import(fixturePath());

    expect($second->accepted)->toBe(0)
        ->and($second->updated)->toBe(51)
        ->and($second->credentials)->toHaveCount(0)
        ->and(Measure::count())->toBe(51)
        ->and(Direction::count())->toBe(28);

    $measure1 = Measure::firstWhere('number', 1);
    expect($measure1->credential->login)->toBe($firstLogin)
        ->and($measure1->credential->password_hash)->toBe($firstHash);
});
