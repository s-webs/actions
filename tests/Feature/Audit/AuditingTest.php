<?php

use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasureStage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use OwenIt\Auditing\Models\Audit;

test('updating a measure field records an audit entry with old and new values', function () {
    $measure = Measure::factory()->create(['title' => 'Исходное название']);

    $measure->update(['title' => 'Новое название']);

    // orderByDesc('id'), not latest()/created_at - the 'created' and 'updated' audit
    // rows can share the same second-precision timestamp.
    $audit = Audit::where('auditable_type', Measure::class)
        ->where('auditable_id', $measure->id)
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->old_values['title'])->toBe('Исходное название')
        ->and($audit->new_values['title'])->toBe('Новое название');
});

test('a change made by an admin (web guard) is attributed to that user', function () {
    $this->seed(RoleSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $stage = MeasureStage::factory()->create();

    $this->actingAs($administrator);
    $stage->update(['title' => 'Переименованный этап']);

    $audit = Audit::where('auditable_type', MeasureStage::class)
        ->where('auditable_id', $stage->id)
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->first();

    expect($audit->user_id)->toBe($administrator->id)
        ->and($audit->user_type)->toBe(User::class);
});

test('a change made through the measure guard is attributed to the credential, not left blank', function () {
    $measure = Measure::factory()->create();
    $credential = MeasureCredential::factory()->create(['measure_id' => $measure->id]);

    $this->actingAs($credential, 'measure');
    $measure->update(['proctor_comment' => 'Обновлено через рабочее место']);

    $audit = Audit::where('auditable_type', Measure::class)
        ->where('auditable_id', $measure->id)
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->first();

    expect($audit->user_id)->toBe($credential->id)
        ->and($audit->user_type)->toBe(MeasureCredential::class);
});

test('rotating a credential does not leak the password hash into the audit trail', function () {
    $measure = Measure::factory()->create();
    $credential = MeasureCredential::factory()->create(['measure_id' => $measure->id, 'password_hash' => Hash::make('old')]);

    $credential->update(['password_hash' => Hash::make('new-password')]);

    $audit = Audit::where('auditable_type', MeasureCredential::class)
        ->where('auditable_id', $credential->id)
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->first();

    expect($audit->new_values)->not->toHaveKey('password_hash')
        ->and($audit->old_values)->not->toHaveKey('password_hash');
});
