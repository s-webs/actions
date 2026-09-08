<?php

use App\Enums\ReviewState;
use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function loginAsMeasure(Measure $measure): MeasureCredential
{
    return MeasureCredential::factory()->create(['measure_id' => $measure->id]);
}

test('the workspace auto-creates a draft row for the current period on first visit', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('measure/workspace')
            ->where('stages.0.id', $stage->id)
            ->where('stages.0.update.review_state', 'draft')
            ->where('measureState.locked', false));

    expect(Period::count())->toBe(1)
        ->and(StagePeriodUpdate::where('measure_stage_id', $stage->id)->exists())->toBeTrue();
});

test('saving a draft updates the stage fact fields without submitting', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    // Visit first so the draft row and current period exist.
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));
    $period = Period::current();

    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'save',
        'measure_status' => 'in_progress',
        'risk_text' => 'Задержка поставки оборудования',
        'needs_decision' => true,
        'stages' => [
            ['id' => $stage->id, 'done_text' => 'Собраны данные', 'next_step' => 'Анализ', 'next_step_date' => null],
        ],
    ])->assertRedirect();

    $update = StagePeriodUpdate::where('measure_stage_id', $stage->id)->where('period_id', $period->id)->first();
    expect($update->done_text)->toBe('Собраны данные')
        ->and($update->review_state)->toBe(ReviewState::Draft);

    $state = $measure->periodStates()->where('period_id', $period->id)->first();
    expect($state->status->value)->toBe('in_progress')
        ->and($state->needs_decision)->toBeTrue();
});

test('submitting requires the submitter name and locks the stages', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'submit',
        'measure_status' => 'in_progress',
        'needs_decision' => false,
        'stages' => [['id' => $stage->id, 'done_text' => 'Готово']],
    ])->assertSessionHasErrors('submitted_by_name');

    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'submit',
        'measure_status' => 'in_progress',
        'needs_decision' => false,
        'submitted_by_name' => 'Иванова А.С., координатор ОП',
        'stages' => [['id' => $stage->id, 'done_text' => 'Готово']],
    ])->assertRedirect();

    $period = Period::current();
    $update = StagePeriodUpdate::where('measure_stage_id', $stage->id)->where('period_id', $period->id)->first();
    expect($update->review_state)->toBe(ReviewState::Submitted)
        ->and($update->submitted_by_name)->toBe('Иванова А.С., координатор ОП')
        ->and($update->submitted_via->value)->toBe('measure_session');

    // Further edits are rejected while awaiting the proctor's decision.
    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'save',
        'measure_status' => 'done',
        'needs_decision' => false,
        'stages' => [['id' => $stage->id, 'done_text' => 'Попытка изменить после подачи']],
    ])->assertSessionHasErrors();

    expect($update->fresh()->done_text)->toBe('Готово');
});

test('a measure session can attach a link as evidence to its own stage', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.workspace.evidence', $stage), [
            'url' => 'https://example.test/order.pdf',
            'title' => 'Приказ №12',
        ])
        ->assertRedirect();

    expect($stage->evidences()->where('title', 'Приказ №12')->exists())->toBeTrue();
});

test('a measure session can attach an uploaded file as evidence', function () {
    Storage::fake('public');

    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.workspace.evidence', $stage), [
            'file' => UploadedFile::fake()->create('order.pdf', 100),
        ])
        ->assertRedirect();

    $evidence = $stage->evidences()->first();
    expect($evidence->type->value)->toBe('file');
    Storage::disk('public')->assertExists($evidence->path_or_url);
});

test('a measure session cannot attach evidence to a stage of a different measure', function () {
    $ownMeasure = Measure::factory()->create();
    $otherMeasure = Measure::factory()->create();
    $otherStage = MeasureStage::factory()->create(['measure_id' => $otherMeasure->id]);
    $credential = loginAsMeasure($ownMeasure);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.workspace.evidence', $otherStage), ['url' => 'https://example.test/x.pdf'])
        ->assertForbidden();
});

test('office and image files are accepted as evidence', function () {
    Storage::fake('public');

    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    foreach (['order.docx', 'plan.xlsx', 'scan.jpg', 'scan.png'] as $name) {
        $stage->evidences()->delete();

        $this->actingAs($credential, 'measure')
            ->post(route('measure.workspace.evidence', $stage), ['file' => UploadedFile::fake()->create($name, 100)])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }
});

test('a disallowed file type is rejected with a validation error', function () {
    Storage::fake('public');

    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.workspace.evidence', $stage), [
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('file');

    expect($stage->evidences()->count())->toBe(0);
});

test('the change log only shows changes for this measure, not another one', function () {
    $measure = Measure::factory()->create(['title' => 'Своё мероприятие']);
    $otherMeasure = Measure::factory()->create(['title' => 'Чужое мероприятие']);
    $credential = loginAsMeasure($measure);

    $measure->update(['proctor_comment' => 'Заметка по своему']);
    $otherMeasure->update(['proctor_comment' => 'Заметка по чужому']);

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('changeLog', function ($entries) {
                return collect($entries)->contains(fn ($e) => $e['model'] === 'Мероприятие')
                    && ! collect($entries)->contains(fn ($e) => ($e['new_values']['proctor_comment'] ?? null) === 'Заметка по чужому');
            }));
});
