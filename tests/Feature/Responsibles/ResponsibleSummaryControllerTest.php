<?php

use App\Models\Measure;
use App\Models\Responsible;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('the summary aggregates each responsible independently', function () {
    $heavy = Responsible::factory()->create(['name' => 'Руководитель УМЦ']);
    $light = Responsible::factory()->create(['name' => 'Декан факультета']);

    Measure::factory()->count(6)->create(['responsible_id' => $heavy->id, 'percent' => 50]);
    Measure::factory()->count(1)->create(['responsible_id' => $light->id, 'percent' => 80]);

    $this->actingAs($this->user)
        ->get(route('responsibles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows', function ($rows) {
                $rows = collect($rows);
                $heavyRow = $rows->firstWhere('name', 'Руководитель УМЦ');
                $lightRow = $rows->firstWhere('name', 'Декан факультета');

                return $heavyRow['count'] === 6
                    && $heavyRow['overloaded'] === true
                    && $lightRow['count'] === 1
                    && $lightRow['overloaded'] === false;
            }));
});

test('measures without a responsible are grouped separately and do not vanish', function () {
    Measure::factory()->create(['responsible_id' => null]);

    $this->actingAs($this->user)
        ->get(route('responsibles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows', fn ($rows) => collect($rows)->firstWhere('name', 'Без ответственного')['count'] === 1));
});

test('a responsible with no measures is omitted rather than shown with all zeros', function () {
    Responsible::factory()->create(['name' => 'Пустой ответственный']);

    $this->actingAs($this->user)
        ->get(route('responsibles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows', fn ($rows) => ! collect($rows)->pluck('name')->contains('Пустой ответственный')));
});
