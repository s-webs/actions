<?php

use App\Models\Measure;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('the dashboard PDF export streams successfully', function () {
    Measure::factory()->count(3)->create();

    $response = $this->actingAs($this->user)->get(route('reports.dashboard-pdf'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the plan xlsx export streams a spreadsheet with a row per measure', function () {
    Measure::factory()->count(3)->create();

    $response = $this->actingAs($this->user)->get(route('reports.plan-xlsx'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheet');
});

test('exports are unreachable by a guest', function () {
    $this->get(route('reports.dashboard-pdf'))->assertRedirect(route('login'));
    $this->get(route('reports.plan-xlsx'))->assertRedirect(route('login'));
});
