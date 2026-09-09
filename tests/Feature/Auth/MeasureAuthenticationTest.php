<?php

use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasureSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('measure credential can log in with the correct login and password', function () {
    $measure = Measure::factory()->create();
    MeasureCredential::factory()->create([
        'measure_id' => $measure->id,
        'login' => 'M-01',
        'password_hash' => Hash::make('correct-horse'),
    ]);

    $response = $this->post('/measure/login', [
        'login' => 'M-01',
        'password' => 'correct-horse',
    ]);

    $response->assertRedirect(route('measure.workspace'));
    $this->assertAuthenticated('measure');
    expect(MeasureSession::where('measure_id', $measure->id)->exists())->toBeTrue();
});

test('measure credential cannot log in with the wrong password', function () {
    $measure = Measure::factory()->create();
    MeasureCredential::factory()->create([
        'measure_id' => $measure->id,
        'login' => 'M-02',
        'password_hash' => Hash::make('correct-horse'),
    ]);

    $response = $this->post('/measure/login', [
        'login' => 'M-02',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('login');
    $this->assertGuest('measure');
});

test('a measure session cannot reach admin-only routes', function () {
    $measure = Measure::factory()->create();
    $credential = MeasureCredential::factory()->create(['measure_id' => $measure->id]);

    $response = $this->actingAs($credential, 'measure')->get('/dashboard');

    $response->assertRedirect(route('login'));
});

test('an admin session cannot reach the measure workspace', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->get('/measure/workspace');

    $response->assertRedirect(route('measure.login'));
});

test('the login link authenticates directly without a login/password form', function () {
    $measure = Measure::factory()->create();
    $credential = MeasureCredential::factory()->create(['measure_id' => $measure->id, 'login_token' => 'a-valid-token']);

    $response = $this->get(route('measure.link-login', 'a-valid-token'));

    $response->assertRedirect(route('measure.workspace'));
    $this->assertAuthenticatedAs($credential, 'measure');
    expect(MeasureSession::where('measure_id', $measure->id)->exists())->toBeTrue();
});

test('an unknown login-link token is rejected', function () {
    $this->get(route('measure.link-login', 'not-a-real-token'))->assertNotFound();
    $this->assertGuest('measure');
});

test('visiting a login link while signed in as another measure switches to the linked one', function () {
    $firstMeasure = Measure::factory()->create();
    $firstCredential = MeasureCredential::factory()->create(['measure_id' => $firstMeasure->id]);
    $secondMeasure = Measure::factory()->create();
    $secondCredential = MeasureCredential::factory()->create(['measure_id' => $secondMeasure->id, 'login_token' => 'switch-token']);

    $this->actingAs($firstCredential, 'measure')
        ->get(route('measure.link-login', 'switch-token'))
        ->assertRedirect(route('measure.workspace'));

    $this->assertAuthenticatedAs($secondCredential, 'measure');
});
