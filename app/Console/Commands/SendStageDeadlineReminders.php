<?php

namespace App\Console\Commands;

use App\Models\MeasureStage;
use App\Models\User;
use App\Notifications\StageDeadlineApproaching;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * За N дней до плановой даты этапа — [[Функциональные требования#4.10 Уведомления и планировщик]].
 * N не зафиксировано документом — 3 дня взято как разумный дефолт (напоминание не
 * впритык к дате, но и не слишком заранее); вынесено в опцию `--days`, если понадобится
 * скорректировать без правки кода.
 */
class SendStageDeadlineReminders extends Command
{
    protected $signature = 'notifications:stage-deadlines {--days=3 : За сколько дней до плановой даты напоминать}';

    protected $description = 'Напомнить о приближающейся плановой дате этапа (контакт мероприятия + администраторы)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $targetDate = now()->addDays($days)->toDateString();

        $stages = MeasureStage::whereDate('planned_date', $targetDate)->with('measure')->get();
        $administrators = User::role(['administrator', 'developer'])->get();

        foreach ($stages as $stage) {
            $stage->measure->notify(new StageDeadlineApproaching($stage));
            Notification::send($administrators, new StageDeadlineApproaching($stage));
        }

        $this->info("Напоминаний отправлено по {$stages->count()} этапам (за {$days} дн. до {$targetDate}).");

        return self::SUCCESS;
    }
}
