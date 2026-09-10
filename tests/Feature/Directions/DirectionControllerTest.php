<?php

use App\Models\Direction;
use App\Models\Measure;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('an administrator can view the directions index', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    Direction::factory()->create(['number' => 1, 'name' => 'Тестовое']);

    $this->actingAs($administrator)->get(route('directions.index'))->assertOk();
});

test('an observer cannot manage directions', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $this->actingAs($observer)->get(route('directions.index'))->assertForbidden();
    $this->actingAs($observer)->get(route('directions.create'))->assertForbidden();
});

test('an administrator can create a direction', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $this->actingAs($administrator)->post(route('directions.store'), [
        'number' => 42,
        'name' => 'Новое направление',
        'in_summary' => true,
    ])->assertRedirect(route('directions.index'));

    $direction = Direction::query()->where('number', 42)->first();
    expect($direction)->not->toBeNull()
        ->and($direction->name)->toBe('Новое направление')
        ->and($direction->in_summary)->toBeTrue();
});

test('an administrator can update a direction', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create(['number' => 5, 'name' => 'Старое', 'in_summary' => false]);

    $this->actingAs($administrator)->put(route('directions.update', $direction), [
        'number' => 5,
        'name' => 'Обновлённое',
        'in_summary' => true,
    ])->assertRedirect(route('directions.index'));

    expect($direction->fresh()->name)->toBe('Обновлённое')
        ->and($direction->fresh()->in_summary)->toBeTrue();
});

test('an administrator can delete a direction without measures', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create();

    $this->actingAs($administrator)
        ->delete(route('directions.destroy', $direction))
        ->assertRedirect(route('directions.index'));

    expect(Direction::query()->whereKey($direction->id)->exists())->toBeFalse();
});

test('a direction with measures cannot be deleted', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $direction = Direction::factory()->create();
    Measure::factory()->create(['direction_id' => $direction->id]);

    $this->actingAs($administrator)
        ->from(route('directions.index'))
        ->delete(route('directions.destroy', $direction))
        ->assertRedirect(route('directions.index'))
        ->assertSessionHasErrors('direction');

    expect(Direction::query()->whereKey($direction->id)->exists())->toBeTrue();
});
