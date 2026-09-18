<?php

use App\Models\Measure;
use App\Models\Responsible;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('an administrator can register a person onto an existing position without moving measures', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $position = Responsible::factory()->create(['name' => 'Проректор по АР']);
    $own = Measure::factory()->create(['number' => 1, 'responsible_id' => $position->id]);
    $other = Measure::factory()->create(['number' => 9]);

    $this->actingAs($administrator)
        ->post(route('responsibles.accounts.store'), [
            'name' => 'Иванов И.И.',
            'email' => 'ivanov@example.com',
            'password' => 'password1',
            'responsible_id' => $position->id,
        ])
        ->assertRedirect(route('responsibles.accounts.index'));

    $user = User::query()->where('email', 'ivanov@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Иванов И.И.')
        ->and($user->hasRole('responsible'))->toBeTrue()
        ->and($position->fresh()->name)->toBe('Проректор по АР')
        ->and($position->fresh()->user_id)->toBe($user->id)
        ->and($own->fresh()->responsible_id)->toBe($position->id)
        ->and($other->fresh()->responsible_id)->not->toBe($position->id);
});

test('measures are attached through the position form, not the person form', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $position = Responsible::factory()->create(['name' => 'Декан']);
    $kept = Measure::factory()->create(['number' => 1, 'responsible_id' => $position->id]);
    $dropped = Measure::factory()->create(['number' => 2, 'responsible_id' => $position->id]);
    $incoming = Measure::factory()->create(['number' => 3]);

    $this->actingAs($administrator)
        ->put(route('responsibles.positions.update', $position), [
            'name' => 'Декан факультета',
            'measure_ids' => [$kept->id, $incoming->id],
        ])
        ->assertRedirect(route('responsibles.accounts.index'));

    expect($position->fresh()->name)->toBe('Декан факультета')
        ->and($kept->fresh()->responsible_id)->toBe($position->id)
        ->and($incoming->fresh()->responsible_id)->toBe($position->id)
        ->and($dropped->fresh()->responsible_id)->toBeNull();
});

test('creating a position can attach measures', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $measure = Measure::factory()->create(['number' => 4]);

    $this->actingAs($administrator)
        ->post(route('responsibles.positions.store'), [
            'name' => 'Руководитель УМЦ',
            'measure_ids' => [$measure->id],
        ])
        ->assertRedirect(route('responsibles.accounts.index'));

    $position = Responsible::query()->where('name', 'Руководитель УМЦ')->first();
    expect($position)->not->toBeNull()
        ->and($measure->fresh()->responsible_id)->toBe($position->id)
        ->and($position->user_id)->toBeNull();
});

test('reassigning a person to another position leaves measures on the old position', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    [$user, $old] = createResponsibleAccount(['email' => 'resp@example.com']);
    $new = Responsible::factory()->create(['name' => 'Другая должность']);
    $measure = Measure::factory()->create(['number' => 1, 'responsible_id' => $old->id]);

    $this->actingAs($administrator)
        ->put(route('responsibles.accounts.update', $old), [
            'name' => $user->name,
            'email' => $user->email,
            'responsible_id' => $new->id,
        ])
        ->assertRedirect(route('responsibles.accounts.index'));

    expect($old->fresh()->user_id)->toBeNull()
        ->and($new->fresh()->user_id)->toBe($user->id)
        ->and($measure->fresh()->responsible_id)->toBe($old->id)
        ->and($user->fresh()->name)->not->toBe($new->name);
});

test('a second person cannot occupy an already taken position', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    [, $position] = createResponsibleAccount(['email' => 'first@example.com']);

    $this->actingAs($administrator)
        ->from(route('responsibles.accounts.create'))
        ->post(route('responsibles.accounts.store'), [
            'name' => 'Петров П.П.',
            'email' => 'second@example.com',
            'password' => 'password1',
            'responsible_id' => $position->id,
        ])
        ->assertRedirect(route('responsibles.accounts.create'))
        ->assertSessionHasErrors('responsible_id');

    expect(User::query()->where('email', 'second@example.com')->exists())->toBeFalse();
});

test('an observer cannot manage positions or accounts', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $this->actingAs($observer)->get(route('responsibles.accounts.index'))->assertForbidden();
    $this->actingAs($observer)->get(route('responsibles.positions.create'))->assertForbidden();
    $this->actingAs($observer)->post(route('responsibles.accounts.store'), [
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'password1',
        'responsible_id' => Responsible::factory()->create()->id,
    ])->assertForbidden();
});

test('a responsible cannot open the accounts module', function () {
    [$user] = createResponsibleAccount(['email' => 'self@example.com']);

    $this->actingAs($user)->get(route('responsibles.accounts.index'))->assertForbidden();
});

test('the index lists the position, occupant and whether there is a login', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    [$user, $withAccount] = createResponsibleAccount(['name' => 'Сидоров С.С.', 'email' => 'has@example.com']);
    $withAccount->update(['name' => 'Декан']);
    $without = Responsible::factory()->create(['name' => 'Вакансия']);

    $this->actingAs($administrator)
        ->get(route('responsibles.accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('responsibles/accounts/index')
            ->where('responsibles', function ($rows) use ($withAccount, $without, $user) {
                $rows = collect($rows);
                $occupied = $rows->firstWhere('id', $withAccount->id);
                $vacant = $rows->firstWhere('id', $without->id);

                return $occupied['has_account'] === true
                    && $occupied['name'] === 'Декан'
                    && $occupied['occupant'] === $user->name
                    && $vacant['has_account'] === false
                    && $vacant['occupant'] === null;
            }));
});

test('the plan registry shows the position and occupant name together', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    [$user, $position] = createResponsibleAccount(['name' => 'Иванов И.И.']);
    $position->update(['name' => 'Проректор по АР']);
    Measure::factory()->create(['number' => 1, 'responsible_id' => $position->id]);

    $this->actingAs($administrator)
        ->get(route('plan.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures.data.0.responsible', 'Проректор по АР · Иванов И.И.'));
});
