<?php

namespace App\Notifications;

use App\Models\MeasureStage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * За N дней до плановой даты этапа — на контакт мероприятия и координатору —
 * [[Функциональные требования#4.10 Уведомления и планировщик]].
 */
class StageDeadlineApproaching extends Notification
{
    use Queueable;

    public function __construct(private readonly MeasureStage $stage) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $measure = $this->stage->measure;

        return (new MailMessage)
            ->subject("Приближается срок этапа — мероприятие №{$measure->number}")
            ->line("Этап «{$this->stage->title}» мероприятия №{$measure->number} «{$measure->title}» — плановая дата {$this->stage->planned_date->format('d.m.Y')}.")
            ->line('Проверьте готовность и подтверждающие документы.');
    }
}
