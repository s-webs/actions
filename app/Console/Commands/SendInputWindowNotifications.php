<?php

namespace App\Console\Commands;

use App\Models\Measure;
use App\Notifications\InputWindowOpened;
use Illuminate\Console\Command;

/**
 * 20 числа — [[Функциональные требования#4.10 Уведомления и планировщик]].
 */
class SendInputWindowNotifications extends Command
{
    protected $signature = 'notifications:input-window-opened';

    protected $description = 'Уведомить контакты мероприятий об открытии окна ввода (20 число)';

    public function handle(): int
    {
        $measures = Measure::whereNotNull('contact_email')->get();

        $measures->each(fn (Measure $m) => $m->notify(new InputWindowOpened($m)));

        $this->info("Отправлено: {$measures->count()} (из ".Measure::count().' мероприятий — без contact_email пропущены).');

        return self::SUCCESS;
    }
}
