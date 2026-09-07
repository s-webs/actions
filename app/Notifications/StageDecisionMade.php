<?php

namespace App\Notifications;

use App\Enums\ReviewState;
use App\Models\StagePeriodUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * После решения проректора (отклонён / на доработку) — на контакт мероприятия —
 * [[Функциональные требования#4.10 Уведомления и планировщик]]. «Утверждён» — не
 * уведомляется отдельно: результат виден в ленте рабочего места (task-007) сразу
 * при следующем визите, а бизнес-правило явно перечисляет только отклонён/на доработку.
 */
class StageDecisionMade extends Notification
{
    use Queueable;

    public function __construct(private readonly StagePeriodUpdate $update) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stage = $this->update->stage;
        $measure = $stage->measure;
        $decision = $this->update->review_state === ReviewState::Rejected ? 'отклонён' : 'возвращён на доработку';

        $mail = (new MailMessage)
            ->subject("Этап {$decision} — мероприятие №{$measure->number}")
            ->line("Этап «{$stage->title}» мероприятия №{$measure->number} «{$measure->title}» {$decision} проректором.");

        if ($this->update->review_comment) {
            $mail->line("Комментарий: {$this->update->review_comment}");
        }

        return $mail;
    }
}
