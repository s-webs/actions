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

test('the registry lists evidence across measures and can filter to one measure', function () {
    $measureA = Measure::factory()->create(['number' => 1]);
    $measureB = Measure::factory()->create(['number' => 2]);
    Evidence::factory()->create(['measure_id' => $measureA->id, 'title' => 'Приказ A']);
    Evidence::factory()->create(['measure_id' => $measureB->id, 'title' => 'Приказ B']);

    $this->actingAs($this->administrator)
        ->get(route('evidence.index'))
        ->assertInertia(fn (Assert $page) => $page->where('evidences.total', 2));

    $this->actingAs($this->administrator)
        ->get(route('evidence.index', ['measure_id' => $measureA->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('evidences.total', 1)
            ->where('evidences.data.0.title', 'Приказ A'));
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
        ->get(route('evidence.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canDelete', false));

    $this->actingAs($this->observer)->delete(route('evidence.destroy', $evidence))->assertForbidden();
    expect(Evidence::find($evidence->id))->not->toBeNull();
});
