<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\RiskDigest;
use App\Services\DashboardSummaryService;
use Illuminate\Console\Command;

/**
 * 26 числа — [[Функциональные требования#4.10 Уведомления и планировщик]]. Использует
 * тот же расчёт «требует решения», что и дашборд/PDF-экспорт (task-009/017,
 * {@see DashboardSummaryService}) — один источник правды на всех трёх поверхностях.
 */
class SendRiskDigest extends Command
{
    protected $signature = 'notifications:risk-digest';

    protected $description = 'Отправить администраторам свод рисков/просрочек за месяц (26 число)';

    public function handle(DashboardSummaryService $summary): int
    {
        $needsDecision = $summary->build()['needsDecision'];

        $recipients = User::role(['administrator', 'developer'])->get();
        $recipients->each(fn (User $u) => $u->notify(new RiskDigest($needsDecision)));

        $this->info("Отправлено {$recipients->count()} администраторам, ".count($needsDecision).' мероприятий в своде.');

        return self::SUCCESS;
    }
}
