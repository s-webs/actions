<?php

use App\Models\Measure;
use App\Models\MeasureStage;
use App\Models\User;
use App\Notifications\InputWindowOpened;
use App\Notifications\RiskDigest;
use App\Notifications\StageDeadlineApproaching;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

test('the input-window command notifies only measures with a contact email', function () {
    Notification::fake();

    $withContact = Measure::factory()->create(['contact_email' => 'contact@example.test']);
    $withoutContact = Measure::factory()->create(['contact_email' => null]);

    $this->artisan('notifications:input-window-opened')->assertSuccessful();

    Notification::assertSentTo($withContact, InputWindowOpened::class);
    Notification::assertNotSentTo($withoutContact, InputWindowOpened::class);
});

test('the risk digest goes to administrators and developers but not observers', function () {
    Notification::fake();
    $this->seed(RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');
    $developer = User::factory()->create();
    $developer->assignRole('developer');
    $observer = User::factory()->create();
    $observer->assignRole('observer');

    $this->artisan('notifications:risk-digest')->assertSuccessful();

    Notification::assertSentTo($administrator, RiskDigest::class);
    Notification::assertSentTo($developer, RiskDigest::class);
    Notification::assertNotSentTo($observer, RiskDigest::class);
});

test('stage deadline reminders only fire for stages exactly N days out', function () {
    Notification::fake();
    $this->seed(RoleSeeder::class);
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $measure = Measure::factory()->create(['contact_email' => 'contact@example.test']);
    $dueSoon = MeasureStage::factory()->create(['measure_id' => $measure->id, 'planned_date' => now()->addDays(3)]);
    $dueLater = MeasureStage::factory()->create(['measure_id' => $measure->id, 'planned_date' => now()->addDays(10)]);

    $this->artisan('notifications:stage-deadlines', ['--days' => 3])->assertSuccessful();

    // Exactly one reminder should fire (for $dueSoon) - not two, even though the
    // measure has a second stage ($dueLater) that isn't due within the window yet.
    Notification::assertSentToTimes($measure, StageDeadlineApproaching::class, 1);
    Notification::assertSentTo($measure, StageDeadlineApproaching::class, fn ($n) => $n->toMail($measure)->subject === "Приближается срок этапа — мероприятие №{$measure->number}");
    Notification::assertSentTo($administrator, StageDeadlineApproaching::class);
});
