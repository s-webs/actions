<?php

use App\Models\Direction;
use App\Models\Measure;
use App\Models\Responsible;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('an administrator can view the create screen', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $this->actingAs($administrator)->get(route('plan.create'))->assertOk();
});

test('an observer cannot view the create screen', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $this->actingAs($observer)->get(route('plan.create'))->assertForbidden();
});

test('a guest is redirected away from the create screen', function () {
    $this->get(route('plan.create'))->assertRedirect(route('login'));
});

test('an administrator can create a measure with credentials', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create();

    $response = $this->actingAs($administrator)->post(route('plan.measures.store'), [
        'number' => 42,
        'title' => 'Новое мероприятие',
        'direction_id' => $direction->id,
        'responsible' => null,
        'deadline' => '2026-12-31',
    ]);

    $response->assertRedirect(route('plan.create'));

    $measure = Measure::query()->where('number', 42)->first();
    expect($measure)->not->toBeNull()
        ->and($measure->title)->toBe('Новое мероприятие')
        ->and($measure->direction_id)->toBe($direction->id)
        ->and($measure->getAttributes())->not->toHaveKey('control_date')
        ->and($measure->credential)->not->toBeNull()
        ->and($measure->credential->login)->toBe('M-42');

    $response->assertSessionHas('credential', fn (array $credential) => $credential['measure_number'] === 42
        && $credential['login'] === 'M-42'
        && is_string($credential['password'])
        && $credential['password'] !== '');
});

test('an administrator can create a measure with optional stages', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create();

    $this->actingAs($administrator)->post(route('plan.measures.store'), [
        'number' => 15,
        'title' => 'С этапами',
        'direction_id' => $direction->id,
        'stages' => [
            ['title' => 'Подготовка', 'planned_date' => '2026-10-01', 'weight' => 40],
            ['title' => 'Реализация', 'planned_date' => null, 'weight' => 60],
        ],
    ])->assertRedirect(route('plan.create'));

    $measure = Measure::query()->where('number', 15)->first();
    expect($measure)->not->toBeNull()
        ->and($measure->stages)->toHaveCount(2)
        ->and($measure->stages[0]->title)->toBe('Подготовка')
        ->and($measure->stages[0]->order)->toBe(1)
        ->and($measure->stages[0]->weight)->toBe(40)
        ->and($measure->stages[1]->title)->toBe('Реализация')
        ->and($measure->stages[1]->order)->toBe(2)
        ->and($measure->stages[1]->weight)->toBe(60);
});

test('creating a measure rejects a stage weight of 100 percent', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create();

    $this->actingAs($administrator)->post(route('plan.measures.store'), [
        'number' => 16,
        'title' => 'Сотня на этапе',
        'direction_id' => $direction->id,
        'stages' => [
            ['title' => 'Финал', 'planned_date' => null, 'weight' => 100],
        ],
    ])->assertSessionHasErrors('stages.0.weight');

    expect(Measure::query()->where('number', 16)->exists())->toBeFalse();
});

test('duplicate measure number is rejected', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create();
    Measure::factory()->create(['number' => 7]);

    $this->actingAs($administrator)->post(route('plan.measures.store'), [
        'number' => 7,
        'title' => 'Дубликат',
        'direction_id' => $direction->id,
    ])->assertSessionHasErrors('number');

    expect(Measure::query()->where('number', 7)->count())->toBe(1);
});

test('an observer cannot create a measure', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');
    $direction = Direction::factory()->create();

    $this->actingAs($observer)->post(route('plan.measures.store'), [
        'number' => 99,
        'title' => 'Запрещено',
        'direction_id' => $direction->id,
    ])->assertForbidden();

    expect(Measure::query()->where('number', 99)->exists())->toBeFalse();
});

test('creating a measure with a catalog responsible attaches that entry', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create();
    $responsible = Responsible::factory()->create(['name' => 'Проректор по АР']);

    $this->actingAs($administrator)->post(route('plan.measures.store'), [
        'number' => 10,
        'title' => 'Первое',
        'direction_id' => $direction->id,
        'responsible_id' => $responsible->id,
    ])->assertRedirect(route('plan.create'));

    $this->actingAs($administrator)->post(route('plan.measures.store'), [
        'number' => 11,
        'title' => 'Второе',
        'direction_id' => $direction->id,
        'responsible_id' => $responsible->id,
    ])->assertRedirect(route('plan.create'));

    expect(Responsible::query()->where('name', 'Проректор по АР')->count())->toBe(1);

    $first = Measure::query()->where('number', 10)->first();
    $second = Measure::query()->where('number', 11)->first();

    expect($first?->responsible?->name)->toBe('Проректор по АР')
        ->and($second?->responsible_id)->toBe($first?->responsible_id);
});
