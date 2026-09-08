<?php

use App\Enums\ReviewState;
use App\Models\Measure;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('administrator');
    $this->observer = User::factory()->create();
    $this->observer->assignRole('observer');
});

test('an administrator can add a stage to a measure', function () {
    $measure = Measure::factory()->create();

    $this->actingAs($this->administrator)
        ->post(route('plan.stages.store', $measure), [
            'title' => 'Согласование ТЗ',
            'planned_date' => '2026-10-01',
            'weight' => 40,
        ])
        ->assertRedirect();

    $stage = $measure->stages()->first();
    expect($stage)->not->toBeNull()
        ->and($stage->title)->toBe('Согласование ТЗ')
        ->and($stage->order)->toBe(1)
        ->and($stage->weight)->toBe(40);
});

test('stage order increments after existing stages', function () {
    $measure = Measure::factory()->create();
    MeasureStage::factory()->create(['measure_id' => $measure->id, 'order' => 1]);

    $this->actingAs($this->administrator)
        ->post(route('plan.stages.store', $measure), ['title' => 'Второй этап', 'weight' => 50])
        ->assertRedirect();

    expect($measure->stages()->orderBy('order')->get()->last()->order)->toBe(2);
});

test('an observer cannot manage stages', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);

    $this->actingAs($this->observer)
        ->post(route('plan.stages.store', $measure), ['title' => 'X', 'weight' => 10])
        ->assertForbidden();

    $this->actingAs($this->observer)
        ->patch(route('plan.stages.update', $stage), ['title' => 'X', 'weight' => 10])
        ->assertForbidden();

    $this->actingAs($this->observer)
        ->delete(route('plan.stages.destroy', $stage))
        ->assertForbidden();
});

test('an administrator can edit a stage', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id, 'title' => 'Старое название', 'weight' => 20]);

    $this->actingAs($this->administrator)
        ->patch(route('plan.stages.update', $stage), [
            'title' => 'Новое название',
            'planned_date' => null,
            'weight' => 60,
        ])
        ->assertRedirect();

    expect($stage->fresh()->title)->toBe('Новое название')
        ->and($stage->fresh()->weight)->toBe(60);
});

test('an administrator can delete a stage with no approved history', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);

    $this->actingAs($this->administrator)
        ->delete(route('plan.stages.destroy', $stage))
        ->assertRedirect();

    expect(MeasureStage::find($stage->id))->toBeNull();
});

test('deleting a stage with approved history is blocked', function () {
    $measure = Measure::factory()->create();
    $stage = MeasureStage::factory()->create(['measure_id' => $measure->id]);
    $period = Period::factory()->create();
    StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $stage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Approved,
    ]);

    $this->actingAs($this->administrator)
        ->delete(route('plan.stages.destroy', $stage))
        ->assertSessionHasErrors('stage');

    expect(MeasureStage::find($stage->id))->not->toBeNull();
});
