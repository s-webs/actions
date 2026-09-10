<?php

namespace App\Console\Commands;

use App\Enums\MeasureStatus;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\Period;
use Illuminate\Console\Command;

/**
 * Ежедневная фиксация просрочки и риска — те же правила, что
 * `Measure::currentStatus()` / `currentRiskLevel()`. Persist нужен, чтобы срез
 * периода при закрытии и сохранённые поля совпадали с экраном.
 */
class MarkOverdueMeasures extends Command
{
    protected $signature = 'measures:mark-overdue';

    protected $description = 'Проставить в текущий период просрочку и риск по дедлайну и этапам';

    public function handle(): int
    {
        $period = Period::current();
        $marked = 0;

        Measure::with(['latestPeriodState', 'stages'])->get()->each(function (Measure $measure) use ($period, &$marked) {
            $status = $measure->currentStatus();
            $risk = $measure->currentRiskLevel();

            if ($measure->risk_level !== $risk) {
                $measure->update(['risk_level' => $risk]);
            }

            if (! in_array($status, [MeasureStatus::Overdue, MeasureStatus::AtRisk, MeasureStatus::Done], true)) {
                return;
            }

            $current = $measure->latestPeriodState?->status;
            if ($current === $status) {
                return;
            }

            MeasurePeriodState::updateOrCreate(
                ['measure_id' => $measure->id, 'period_id' => $period->id],
                ['status' => $status],
            );

            $marked++;
        });

        $this->info("Обновлено статусов периода: {$marked}.");

        return self::SUCCESS;
    }
}
