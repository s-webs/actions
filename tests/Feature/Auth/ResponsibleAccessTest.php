<?php

use App\Enums\EvidenceType;
use App\Enums\ReviewState;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\Responsible;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('a responsible can log in through the admin form and reach the dashboard', function () {
    [$user] = createResponsibleAccount(['email' => 'resp@example.com']);

    $this->post('/login', [
        'email' => 'resp@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('a responsible sees only own measures on the plan and dashboard', function () {
    [$user, $profile] = createResponsibleAccount();
    $own = Measure::factory()->create(['number' => 4, 'responsible_id' => $profile->id]);
    Measure::factory()->create(['number' => 8, 'responsible_id' => Responsible::factory()]);

    $this->actingAs($user)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$own->id])
            ->where('canCreate', false)
            ->where('canImport', false)
            ->where('canManageStages', false));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('kpis.total', 1));
});

test('an observer still sees the full plan', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');
    Measure::factory()->count(2)->sequence(fn ($seq) => ['number' => $seq->index + 1])->create();

    $this->actingAs($observer)
        ->get(route('plan.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('measures.total', 2));
});

test('a responsible can approve a stage of an owned measure but not a foreign one', function () {
    [$user, $profile] = createResponsibleAccount();
    $ownStage = MeasureStage::factory()->create([
        'measure_id' => Measure::factory()->create(['responsible_id' => $profile->id])->id,
    ]);
    $foreignStage = MeasureStage::factory()->create();
    $period = Period::factory()->create();

    $ownUpdate = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $ownStage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);
    $foreignUpdate = StagePeriodUpdate::factory()->create([
        'measure_stage_id' => $foreignStage->id,
        'period_id' => $period->id,
        'review_state' => ReviewState::Submitted,
    ]);

    $this->actingAs($user)->get(route('approval.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('updates', fn ($rows) => collect($rows)->pluck('id')->all() === [$ownUpdate->id]));

    $this->actingAs($user)->post(route('approval.approve', $ownUpdate))->assertRedirect();
    $this->actingAs($user)->post(route('approval.approve', $foreignUpdate))->assertForbidden();
});

test('a responsible can rotate credentials of own measures only', function () {
    [$user, $profile] = createResponsibleAccount();
    $own = Measure::factory()->create(['responsible_id' => $profile->id]);
    $foreign = Measure::factory()->create();
    MeasureCredential::factory()->create(['measure_id' => $own->id, 'password' => 'own-old']);
    MeasureCredential::factory()->create(['measure_id' => $foreign->id, 'password' => 'foreign-old']);

    $this->actingAs($user)->get(route('credentials.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows', fn ($rows) => collect($rows)->pluck('id')->all() === [$own->id]));

    $this->actingAs($user)->post(route('credentials.rotate', $own))->assertRedirect();
    $this->actingAs($user)->post(route('credentials.rotate', $foreign))->assertForbidden();

    expect($own->credential()->first()->password)->not->toBe('own-old')
        ->and($foreign->credential()->first()->password)->toBe('foreign-old');
});

test('a responsible can delete evidence of own measures only', function () {
    [$user, $profile] = createResponsibleAccount();
    $own = Measure::factory()->create(['responsible_id' => $profile->id]);
    $foreign = Measure::factory()->create();
    $ownEvidence = Evidence::factory()->create(['measure_id' => $own->id, 'type' => EvidenceType::Link]);
    $foreignEvidence = Evidence::factory()->create(['measure_id' => $foreign->id, 'type' => EvidenceType::Link]);

    $this->actingAs($user)->get(route('evidence.show', $foreign))->assertForbidden();
    $this->actingAs($user)->delete(route('evidence.destroy', $ownEvidence))->assertRedirect();
    $this->actingAs($user)->delete(route('evidence.destroy', $foreignEvidence))->assertForbidden();

    expect(Evidence::find($ownEvidence->id))->toBeNull()
        ->and(Evidence::find($foreignEvidence->id))->not->toBeNull();
});

test('a responsible is forbidden from admin-only modules', function () {
    [$user] = createResponsibleAccount();

    $this->actingAs($user)->get(route('plan.import'))->assertForbidden();
    $this->actingAs($user)->get(route('plan.create'))->assertForbidden();
    $this->actingAs($user)->get(route('periods.index'))->assertForbidden();
    $this->actingAs($user)->get(route('directions.index'))->assertForbidden();
    $this->actingAs($user)->get(route('responsibles.index'))->assertForbidden();
    $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
});

test('a responsible cannot accept work or override stages', function () {
    [$user, $profile] = createResponsibleAccount();
    $measure = Measure::factory()->create(['responsible_id' => $profile->id]);

    $this->actingAs($user)
        ->post(route('plan.stages.store', $measure), [
            'title' => 'Этап',
            'weight' => 40,
        ])
        ->assertForbidden();

    $this->actingAs($user)->post(route('plan.measures.accept', $measure))->assertForbidden();
});

test('measure login for executors still works independently of responsible accounts', function () {
    $measure = Measure::factory()->create();
    MeasureCredential::factory()->create([
        'measure_id' => $measure->id,
        'login' => 'M-77',
        'password_hash' => Hash::make('secret-pass'),
    ]);

    $this->post('/measure/login', [
        'login' => 'M-77',
        'password' => 'secret-pass',
    ])->assertRedirect(route('measure.workspace'));

    $this->assertAuthenticated('measure');
});
