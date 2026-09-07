<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('a coordinator can view the import screen', function () {
    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');

    $this->actingAs($coordinator)->get(route('plan.import'))->assertOk();
});

test('an observer cannot view the import screen', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $this->actingAs($observer)->get(route('plan.import'))->assertForbidden();
});

test('a guest is redirected away from the import screen', function () {
    $this->get(route('plan.import'))->assertRedirect(route('login'));
});
