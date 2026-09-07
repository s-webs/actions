<?php

use App\Models\Direction;
use App\Models\Measure;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the audit log lists changes and can be filtered by model', function () {
    $user = User::factory()->create();
    $measure = Measure::factory()->create();
    $direction = Direction::factory()->create();

    $measure->update(['title' => 'Изменено']);
    $direction->update(['name' => 'Другое направление']);

    $this->actingAs($user)
        ->get(route('audit.index', ['model' => Measure::class]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audits.data', fn ($rows) => collect($rows)->every(fn ($r) => $r['model'] === 'Мероприятие')));
});

test('a guest cannot view the audit log', function () {
    $this->get(route('audit.index'))->assertRedirect(route('login'));
});
