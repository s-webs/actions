<?php

use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasureSession;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('administrator');
    $this->observer = User::factory()->create();
    $this->observer->assignRole('observer');
});

test('an observer cannot manage credentials', function () {
    $this->actingAs($this->observer)->get(route('credentials.index'))->assertForbidden();
    $this->actingAs($this->observer)->post(route('credentials.rotate-all'))->assertForbidden();
});

test('rotating a credential replaces its hash and readable password, bumps rotated_at, and rotates the login token', function () {
    $measure = Measure::factory()->create();
    $credential = MeasureCredential::factory()->create([
        'measure_id' => $measure->id,
        'password_hash' => Hash::make('old-password'),
        'password' => 'old-password',
        'login_token' => 'old-token',
    ]);

    $this->actingAs($this->administrator)
        ->post(route('credentials.rotate', $measure))
        ->assertRedirect();

    $fresh = $credential->fresh();
    expect($fresh->rotated_at)->not->toBeNull()
        ->and($fresh->rotated_by)->toBe($this->administrator->id)
        ->and(Hash::check('old-password', $fresh->password_hash))->toBeFalse()
        ->and($fresh->password)->not->toBe('old-password')
        ->and($fresh->login_token)->not->toBe('old-token');
});

test('the credentials index always shows the readable password and a login link, not just once', function () {
    $measure = Measure::factory()->create(['number' => 7]);
    MeasureCredential::factory()->create([
        'measure_id' => $measure->id,
        'login' => 'M-07',
        'password' => 'visible-password',
        'login_token' => 'visible-token',
    ]);

    $this->actingAs($this->administrator)
        ->get(route('credentials.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.login', 'M-07')
            ->where('rows.0.password', 'visible-password')
            ->where('rows.0.login_url', route('measure.link-login', 'visible-token')));
});

test('rotating a credential closes its active framework sessions but keeps the audit log', function () {
    $measure = Measure::factory()->create();
    MeasureCredential::factory()->create(['measure_id' => $measure->id]);
    DB::table('sessions')->insert(['id' => 'sess-1', 'payload' => 'x', 'last_activity' => time()]);
    MeasureSession::factory()->create(['measure_id' => $measure->id, 'session_id' => 'sess-1', 'started_at' => now()]);

    $this->actingAs($this->administrator)->post(route('credentials.rotate', $measure));

    expect(DB::table('sessions')->where('id', 'sess-1')->exists())->toBeFalse()
        ->and(MeasureSession::where('measure_id', $measure->id)->exists())->toBeTrue();
});

test('terminating sessions without rotating leaves the password hash untouched', function () {
    $measure = Measure::factory()->create();
    $credential = MeasureCredential::factory()->create(['measure_id' => $measure->id]);
    $originalHash = $credential->password_hash;
    DB::table('sessions')->insert(['id' => 'sess-2', 'payload' => 'x', 'last_activity' => time()]);
    MeasureSession::factory()->create(['measure_id' => $measure->id, 'session_id' => 'sess-2', 'started_at' => now()]);

    $this->actingAs($this->administrator)->post(route('credentials.terminate-sessions', $measure));

    expect(DB::table('sessions')->where('id', 'sess-2')->exists())->toBeFalse()
        ->and($credential->fresh()->password_hash)->toBe($originalHash);
});

test('rotate-all regenerates every measure', function () {
    $measures = Measure::factory()->count(3)->sequence(fn ($seq) => ['number' => $seq->index + 1])->create()
        ->each(fn (Measure $m) => MeasureCredential::factory()->create(['measure_id' => $m->id, 'password' => 'stale', 'login_token' => 'stale-token-'.$m->id]));

    $this->actingAs($this->administrator)
        ->post(route('credentials.rotate-all'))
        ->assertRedirect();

    foreach ($measures as $measure) {
        $credential = $measure->credential()->first();
        expect($credential->password)->not->toBe('stale')
            ->and($credential->login_token)->not->toBe('stale-token');
    }
});
