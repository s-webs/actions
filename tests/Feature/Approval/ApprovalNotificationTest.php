<?php

use App\Enums\EvidenceType;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use App\Notifications\StageDecisionMade;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('administrator');
});

test('rejecting a stage notifies the measure contact', function () {
    Notification::fake();

    $measure = Measure::factory()->create(['contact_email' => 'contact@example.test']);
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => Period::factory(),
        'review_state' => 'submitted',
    ]);

    $this->actingAs($this->administrator)->post(route('approval.reject', $update), ['review_comment' => 'Недостаточно данных']);

    Notification::assertSentTo($measure, StageDecisionMade::class);
});

test('sending a stage to rework notifies the measure contact', function () {
    Notification::fake();

    $measure = Measure::factory()->create(['contact_email' => 'contact@example.test']);
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => Period::factory(),
        'review_state' => 'submitted',
    ]);

    $this->actingAs($this->administrator)->post(route('approval.rework', $update), ['review_comment' => 'Уточните формулировку']);

    Notification::assertSentTo($measure, StageDecisionMade::class);
});

test('approving a stage does not fire a decision notification', function () {
    Notification::fake();

    $measure = Measure::factory()->create(['contact_email' => 'contact@example.test']);
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'weight' => 100]);
    $period = Period::factory()->create();
    $update = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => 'submitted',
    ]);
    Evidence::factory()->create(['measure_id' => $measure->id, 'measure_stage_id' => $stage->id, 'period_id' => $period->id, 'type' => EvidenceType::Link]);

    $this->actingAs($this->administrator)->post(route('approval.approve', $update), ['approved_percent' => 100]);

    Notification::assertNothingSentTo($measure);
});
