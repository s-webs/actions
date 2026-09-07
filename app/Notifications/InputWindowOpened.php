<?php

namespace App\Notifications;

use App\Models\Measure;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 20 числа — на контакт мероприятия: открыто окно ввода —
 * [[Функциональные требования#4.10 Уведомления и планировщик]].
 */
class InputWindowOpened extends Notification
{
    use Queueable;

    public function __construct(private readonly Measure $measure) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Открыто окно ввода — мероприятие №{$this->measure->number}")
            ->line("По мероприятию №{$this->measure->number} «{$this->measure->title}» открыто окно ввода данных за текущий период (20–23 число).")
            ->line('Внесите факты, статус и подтверждающие документы, затем отправьте этап на проверку.');
    }
}
