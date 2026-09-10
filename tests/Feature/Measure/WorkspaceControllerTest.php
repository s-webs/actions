<?php

use App\Enums\ReviewState;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function loginAsMeasure(Measure $measure): MeasureCredential
{
    return MeasureCredential::factory()->create(['measure_id' => $measure->id]);
}

function confirmStages(MeasureCredential $credential): void
{
    test()->actingAs($credential, 'measure')->post(route('measure.stages.confirm'))->assertRedirect();
}

/** Administrator used to approve/reject the current stage in the sequential-progression tests. */
function adminUser(): User
{
    test()->seed(RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('administrator');

    return $user;
}

test('before confirmation the workspace shows the stage setup screen, no draft rows', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('measure/workspace')
            ->where('stagesConfirmed', false)
            ->where('stageList.0.id', $stage->id)
            ->where('currentStage', null));

    expect(StagePeriodUpdate::count())->toBe(0);
});

test('confirming the stage list requires at least one stage', function () {
    $measure = Measure::factory()->create();
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.stages.confirm'))
        ->assertSessionHasErrors('stages');

    expect($measure->fresh()->stages_confirmed_at)->toBeNull();
});

test('confirming the stage list locks the structure and reveals the first stage as current', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1]);
    $credential = loginAsMeasure($measure);

    confirmStages($credential);

    expect($measure->fresh()->stages_confirmed_at)->not->toBeNull();

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stagesConfirmed', true)
            ->where('currentStage.id', $stage->id)
            ->where('stageList.0.status', 'current'));

    expect(StagePeriodUpdate::where('measure_stage_id', $stage->id)->exists())->toBeTrue();
});

test('after confirmation, stage structure can no longer be managed from the measure guard', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    confirmStages($credential);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.stages.store'), ['title' => 'X', 'weight' => 10])
        ->assertForbidden();

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.stages.update', $stage), ['title' => 'X', 'weight' => 10])
        ->assertForbidden();

    $this->actingAs($credential, 'measure')
        ->delete(route('measure.stages.destroy', $stage))
        ->assertForbidden();
});

test('the second stage stays locked and unreachable until the first is approved', function () {
    $measure = Measure::factory()->create();
    $first = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 50]);
    $second = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 50]);
    $credential = loginAsMeasure($measure);

    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentStage.id', $first->id)
            ->where('stageList.1.status', 'locked')
            ->has('completedStages', 0));
});

test('approving the current stage opens the next one', function () {
    $measure = Measure::factory()->create();
    $first = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 50]);
    $second = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 50]);
    $credential = loginAsMeasure($measure);
    $admin = adminUser();

    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));
    $period = Period::current();

    $update = StagePeriodUpdate::where('measure_stage_id', $first->id)->where('period_id', $period->id)->first();
    Evidence::factory()->create(['measure_id' => $measure->id, 'measure_stage_id' => $first->id, 'period_id' => $period->id]);
    $update->update(['review_state' => ReviewState::Submitted]);

    $this->actingAs($admin, 'web')->post(route('approval.approve', $update))->assertRedirect();

    expect($measure->fresh()->currentStage()?->id)->toBe($second->id);

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page->where('currentStage.id', $second->id));
});

test('a completed stage is sent as a read-only card payload with its report and documents', function () {
    $measure = Measure::factory()->create();
    $first = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1, 'weight' => 50, 'title' => 'Подготовка']);
    $second = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2, 'weight' => 50]);
    $credential = loginAsMeasure($measure);
    $admin = adminUser();

    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));
    $period = Period::current();

    $update = StagePeriodUpdate::where('measure_stage_id', $first->id)->where('period_id', $period->id)->first();
    $evidence = Evidence::factory()->create([
        'measure_id' => $measure->id,
        'measure_stage_id' => $first->id,
        'period_id' => $period->id,
        'title' => 'Протокол',
        'path_or_url' => 'https://example.test/protocol.pdf',
    ]);
    $update->update([
        'review_state' => ReviewState::Submitted,
        'done_text' => 'Собраны данные',
        'submitted_by_name' => 'Иванова А.С.',
        'submitted_at' => now(),
    ]);

    $this->actingAs($admin, 'web')->post(route('approval.approve', $update))->assertRedirect();

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentStage.id', $second->id)
            ->where('stageList.0.status', 'completed')
            ->where('stageList.1.status', 'current')
            ->has('completedStages', 1)
            ->where('completedStages.0.id', $first->id)
            ->where('completedStages.0.title', 'Подготовка')
            ->where('completedStages.0.update.done_text', 'Собраны данные')
            ->where('completedStages.0.update.approved_percent', 50)
            ->where('completedStages.0.update.submitted_by_name', 'Иванова А.С.')
            ->where('completedStages.0.evidences.0.id', $evidence->id)
            ->where('completedStages.0.evidences.0.title', 'Протокол'));
});

test('rejecting the current stage does not advance to the next one', function () {
    $measure = Measure::factory()->create();
    $first = MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1]);
    MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 2]);
    $credential = loginAsMeasure($measure);
    $admin = adminUser();

    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));
    $period = Period::current();

    $update = StagePeriodUpdate::where('measure_stage_id', $first->id)->where('period_id', $period->id)->first();
    $update->update(['review_state' => ReviewState::Submitted]);

    $this->actingAs($admin, 'web')->post(route('approval.reject', $update), ['review_comment' => 'Недостаточно данных'])->assertRedirect();

    expect($measure->fresh()->currentStage()?->id)->toBe($first->id);
});

test('once every stage is approved the workspace shows completion instead of a form', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'weight' => 95]);
    $credential = loginAsMeasure($measure);
    $admin = adminUser();

    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));
    $period = Period::current();

    $update = StagePeriodUpdate::where('measure_stage_id', $stage->id)->where('period_id', $period->id)->first();
    Evidence::factory()->create(['measure_id' => $measure->id, 'measure_stage_id' => $stage->id, 'period_id' => $period->id]);
    $update->update(['review_state' => ReviewState::Submitted]);
    $this->actingAs($admin, 'web')->post(route('approval.approve', $update));

    $this->actingAs($credential, 'measure')
        ->get(route('measure.workspace'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentStage', null)
            ->has('completedStages', 1)
            ->where('completedStages.0.id', $stage->id));

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.workspace.update'), ['action' => 'save', 'measure_status' => 'done', 'needs_decision' => false])
        ->assertForbidden();
});

test('the executor cannot set done, overdue or at_risk from the workspace', function () {
    $measure = Measure::factory()->create(['deadline' => now()->addMonths(6)]);
    MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);
    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    foreach (['done', 'overdue', 'at_risk'] as $status) {
        $this->actingAs($credential, 'measure')
            ->patch(route('measure.workspace.update'), [
                'action' => 'save',
                'measure_status' => $status,
                'needs_decision' => false,
            ])
            ->assertSessionHasErrors('measure_status');
    }
});

test('the workspace update route is unreachable before the stage list is confirmed', function () {
    $measure = Measure::factory()->create();
    MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.workspace.update'), ['action' => 'save', 'measure_status' => 'in_progress', 'needs_decision' => false])
        ->assertForbidden();
});

test('saving a draft updates the current stage fact fields without submitting', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));
    $period = Period::current();

    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'save',
        'measure_status' => 'in_progress',
        'risk_text' => 'Задержка поставки оборудования',
        'needs_decision' => true,
        'done_text' => 'Собраны данные',
    ])->assertRedirect();

    $update = StagePeriodUpdate::where('measure_stage_id', $stage->id)->where('period_id', $period->id)->first();
    expect($update->done_text)->toBe('Собраны данные')
        ->and($update->review_state)->toBe(ReviewState::Draft);

    $state = $measure->periodStates()->where('period_id', $period->id)->first();
    expect($state->status->value)->toBe('in_progress')
        ->and($state->needs_decision)->toBeTrue();
});

test('submitting requires the submitter name and locks the current stage', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);

    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'submit',
        'measure_status' => 'in_progress',
        'needs_decision' => false,
        'done_text' => 'Готово',
    ])->assertSessionHasErrors('submitted_by_name');

    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'submit',
        'measure_status' => 'in_progress',
        'needs_decision' => false,
        'submitted_by_name' => 'Иванова А.С.',
        'done_text' => 'Готово',
    ])->assertRedirect();

    $period = Period::current();
    $update = StagePeriodUpdate::where('measure_stage_id', $stage->id)->where('period_id', $period->id)->first();
    expect($update->review_state)->toBe(ReviewState::Submitted)
        ->and($update->submitted_by_name)->toBe('Иванова А.С.')
        ->and($update->submitted_via->value)->toBe('measure_session');

    // Further edits are rejected while awaiting the administrator's decision.
    $this->actingAs($credential, 'measure')->patch(route('measure.workspace.update'), [
        'action' => 'save',
        'measure_status' => 'done',
        'needs_decision' => false,
        'done_text' => 'Попытка изменить после подачи',
    ])->assertSessionHasErrors();

    expect($update->fresh()->done_text)->toBe('Готово');
});

function draftPayload(array $overrides = []): array
{
    return array_merge([
        'action' => 'save',
        'measure_status' => 'in_progress',
        'needs_decision' => false,
    ], $overrides);
}

test('a measure session can attach a link as evidence to its own current stage, in the same request as the report', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);
    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.workspace.update'), draftPayload(['evidence_url' => 'https://example.test/order.pdf']))
        ->assertRedirect();

    expect($stage->evidences()->where('path_or_url', 'https://example.test/order.pdf')->exists())->toBeTrue();
});

test('a measure session can attach an uploaded file as evidence', function () {
    Storage::fake('public');

    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);
    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.workspace.update'), draftPayload(['files' => [UploadedFile::fake()->create('order.pdf', 100)]]))
        ->assertRedirect();

    $evidence = $stage->evidences()->first();
    expect($evidence->type->value)->toBe('file');
    Storage::disk('public')->assertExists($evidence->path_or_url);
});

test('a measure session can attach several files at once', function () {
    Storage::fake('public');

    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);
    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.workspace.update'), draftPayload([
            'files' => [
                UploadedFile::fake()->create('order.pdf', 100),
                UploadedFile::fake()->create('scan.jpg', 100),
                UploadedFile::fake()->create('report.docx', 100),
            ],
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($stage->evidences()->count())->toBe(3);
    $stage->evidences->each(fn ($e) => Storage::disk('public')->assertExists($e->path_or_url));
});

test('office and image files are accepted as evidence', function () {
    Storage::fake('public');

    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);
    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    foreach (['order.docx', 'plan.xlsx', 'scan.jpg', 'scan.png'] as $name) {
        $stage->evidences()->delete();

        $this->actingAs($credential, 'measure')
            ->patch(route('measure.workspace.update'), draftPayload(['files' => [UploadedFile::fake()->create($name, 100)]]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }
});

test('a disallowed file type is rejected with a validation error', function () {
    Storage::fake('public');

    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $credential = loginAsMeasure($measure);
    confirmStages($credential);
    $this->actingAs($credential, 'measure')->get(route('measure.workspace'));

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.workspace.update'), draftPayload([
            'files' => [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
        ]))
        ->assertSessionHasErrors('files.0');

    expect($stage->evidences()->count())->toBe(0);
});

test('a measure session can create a stage for its own measure', function () {
    $measure = Measure::factory()->create();
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.stages.store'), [
            'title' => 'Сбор данных',
            'planned_date' => '2026-10-05',
            'weight' => 50,
        ])
        ->assertRedirect();

    $stage = $measure->stages()->first();
    expect($stage)->not->toBeNull()
        ->and($stage->title)->toBe('Сбор данных')
        ->and($stage->order)->toBe(1);
});

test('a measure session cannot set a stage weight of 100 percent', function () {
    $measure = Measure::factory()->create();
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->post(route('measure.stages.store'), [
            'title' => 'Финал',
            'weight' => 100,
        ])
        ->assertSessionHasErrors('weight');

    expect($measure->stages()->count())->toBe(0);
});

test('a measure session can edit and delete its own stage', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'title' => 'Старое', 'weight' => 20]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.stages.update', $stage), ['title' => 'Новое', 'planned_date' => null, 'weight' => 70])
        ->assertRedirect();

    expect($stage->fresh()->title)->toBe('Новое')->and($stage->fresh()->weight)->toBe(70);

    $this->actingAs($credential, 'measure')
        ->delete(route('measure.stages.destroy', $stage))
        ->assertRedirect();

    expect(MeasureStage::find($stage->id))->toBeNull();
});

test('a measure session cannot edit or delete a stage belonging to another measure', function () {
    $ownMeasure = Measure::factory()->create();
    $otherMeasure = Measure::factory()->create();
    $otherStage = MeasureStage::factory()->create(['measure_id' => $otherMeasure->id]);
    $credential = loginAsMeasure($ownMeasure);

    $this->actingAs($credential, 'measure')
        ->patch(route('measure.stages.update', $otherStage), ['title' => 'X', 'weight' => 10])
        ->assertForbidden();

    $this->actingAs($credential, 'measure')
        ->delete(route('measure.stages.destroy', $otherStage))
        ->assertForbidden();

    expect(MeasureStage::find($otherStage->id))->not->toBeNull();
});

test('a measure session cannot delete its own stage once it has approved history', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $period = Period::factory()->create();
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Approved,
    ]);
    $credential = loginAsMeasure($measure);

    $this->actingAs($credential, 'measure')
        ->delete(route('measure.stages.destroy', $stage))
        ->assertSessionHasErrors('stage');

    expect(MeasureStage::find($stage->id))->not->toBeNull();
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
