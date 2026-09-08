<?php

use App\Models\Measure;
use App\Models\Period;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/**
 * `Gate::before` в `AppServiceProvider` — роль `developer` проходит любую
 * Policy-проверку без исключений. Проверяем на двух независимых Policy (не связанных
 * общей ролью в правилах), чтобы убедиться, что это действительно глобальный bypass,
 * а не совпадение с одной конкретной проверкой.
 */
test('a developer without the administrator role can still import the plan', function () {
    $developer = User::factory()->create();
    $developer->assignRole('developer');

    $this->actingAs($developer)->get(route('plan.import'))->assertOk();
});

test('a developer without the administrator role can still close a period', function () {
    $developer = User::factory()->create();
    $developer->assignRole('developer');
    Measure::factory()->create();
    Period::current();

    $this->actingAs($developer)->post(route('periods.close'))->assertRedirect();
});

test('a plain observer is not granted developer-level bypass', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $this->actingAs($observer)->get(route('plan.import'))->assertForbidden();
});
