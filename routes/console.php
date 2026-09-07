<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Правило 1 · Автопросрочка — [[Бизнес-правила#Правило 1 · Автопросрочка]].
// В проде требуется реальный cron, вызывающий `php artisan schedule:run` каждую минуту
// (см. [[Стек#Нефункциональные требования]] — Laravel Scheduler).
Schedule::command('measures:mark-overdue')->dailyAt('01:00');
