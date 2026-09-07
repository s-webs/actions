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

// Цикл уведомлений — [[Функциональные требования#4.10 Уведомления и планировщик]].
Schedule::command('notifications:input-window-opened')->monthlyOn(20, '08:00');
Schedule::command('notifications:risk-digest')->monthlyOn(26, '08:00');
Schedule::command('notifications:stage-deadlines')->dailyAt('07:00');
