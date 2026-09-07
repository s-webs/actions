<?php

namespace App\Console\Commands;

use App\Enums\MeasureStatus;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\Period;
use Illuminate\Console\Command;

/**
 * Ежедневная автопросрочка — [[Бизнес-правила#Правило 1 · Автопросрочка]]. Persist-версия
 * дублирует то, что `Measure::currentStatus()` уже считает "вживую" при каждом чтении
 * (task-009) — так что этот джоб не критичен для отображения день в день, но нужен, чтобы:
 * (а) SQL-фильтры (`PlanController`, статус `overdue`) видели то же, что видит дашборд —
 *     они читают только сохранённый статус, не пересчитывают его на лету;
 * (б) срез периода при закрытии (task-016) фиксировал «просрочено», а не последний
 *     введённый вручную статус.
 *
 * Уведомление ответственного — вне периметра этой задачи, см. task-018.
 */
class MarkOverdueMeasures extends Command
{
    protected $signature = 'measures:mark-overdue';

    protected $description = 'Проставить статус «Просрочено» текущего периода мероприятиям с истёкшим сроком';

    public function handle(): int
    {
        $period = Period::current();
        $marked = 0;

        Measure::with('latestPeriodState')->get()->each(function (Measure $measure) use ($period, &$marked) {
            $isPastDue = $measure->deadline?->isPast() || $measure->control_date?->isPast();
            $currentStatus = $measure->latestPeriodState?->status ?? MeasureStatus::NotStarted;

            if (! $isPastDue || $currentStatus === MeasureStatus::Done || $currentStatus === MeasureStatus::Overdue) {
                return;
            }

            MeasurePeriodState::updateOrCreate(
                ['measure_id' => $measure->id, 'period_id' => $period->id],
                ['status' => MeasureStatus::Overdue],
            );

            $marked++;
        });

        $this->info("Помечено просроченными: {$marked}.");

        return self::SUCCESS;
    }
}
