<?php

use App\Enums\EvidenceType;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('administrator');
    $this->observer = User::factory()->create();
    $this->observer->assignRole('observer');
});

test('the registry lists measures with evidence counts', function () {
    $measureA = Measure::factory()->create(['number' => 1]);
    $measureB = Measure::factory()->create(['number' => 2]);
    Evidence::factory()->count(2)->create(['measure_id' => $measureA->id]);
    Evidence::factory()->create(['measure_id' => $measureB->id]);

    $this->actingAs($this->administrator)
        ->get(route('evidence.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('evidence/index')
            ->has('measures', 2)
            ->where('measures.0.id', $measureA->id)
            ->where('measures.0.evidences_count', 2)
            ->where('measures.1.id', $measureB->id)
            ->where('measures.1.evidences_count', 1)
            ->missing('evidences'));
});

test('the measure page groups files by upload date', function () {
    $measure = Measure::factory()->create(['number' => 3, 'title' => 'Протоколы']);
    $other = Measure::factory()->create();

    $morning = Evidence::factory()->create(['measure_id' => $measure->id, 'title' => 'Утро']);
    $morning->forceFill(['created_at' => '2026-09-17 10:00:00'])->save();

    $evening = Evidence::factory()->create(['measure_id' => $measure->id, 'title' => 'Вечер']);
    $evening->forceFill(['created_at' => '2026-09-17 18:00:00'])->save();

    $previous = Evidence::factory()->create(['measure_id' => $measure->id, 'title' => 'Вчера']);
    $previous->forceFill(['created_at' => '2026-09-16 12:00:00'])->save();

    Evidence::factory()->create(['measure_id' => $other->id, 'title' => 'Чужой']);

    $this->actingAs($this->administrator)
        ->get(route('evidence.show', $measure))
        ->assertInertia(fn (Assert $page) => $page
            ->component('evidence/show')
            ->where('measure.id', $measure->id)
            ->where('measure.number', 3)
            ->has('groups', 2)
            ->where('groups.0.date', '2026-09-17')
            ->where('groups.0.label', '17.09.2026')
            ->has('groups.0.files', 2)
            ->where('groups.0.files.0.title', 'Вечер')
            ->where('groups.0.files.1.title', 'Утро')
            ->where('groups.1.date', '2026-09-16')
            ->where('groups.1.label', '16.09.2026')
            ->has('groups.1.files', 1)
            ->where('groups.1.files.0.title', 'Вчера')
            ->where('canDelete', true));
});

test('an administrator can delete evidence and its stored file', function () {
    Storage::fake('public');
    $path = 'evidence/1/order.pdf';
    Storage::disk('public')->put($path, 'contents');

    $measure = Measure::factory()->create();
    $evidence = Evidence::factory()->create(['measure_id' => $measure->id, 'type' => EvidenceType::File, 'path_or_url' => $path]);

    $this->actingAs($this->administrator)->delete(route('evidence.destroy', $evidence))->assertRedirect();

    expect(Evidence::find($evidence->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('an observer cannot delete evidence and does not see a delete option', function () {
    $measure = Measure::factory()->create();
    $evidence = Evidence::factory()->create(['measure_id' => $measure->id]);

    $this->actingAs($this->observer)
        ->get(route('evidence.show', $measure))
        ->assertInertia(fn (Assert $page) => $page->where('canDelete', false));

    $this->actingAs($this->observer)->delete(route('evidence.destroy', $evidence))->assertForbidden();
    expect(Evidence::find($evidence->id))->not->toBeNull();
});
