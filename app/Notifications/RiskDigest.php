<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 26 числа — проректору и координатору: сформирован перечень рисков/просрочек/
 * «требует решения» — [[Функциональные требования#4.10 Уведомления и планировщик]].
 */
class RiskDigest extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{number: int, title: string, deadline: ?string, percent: int, problem: ?string}>  $needsDecision
     */
    public function __construct(private readonly array $needsDecision) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Свод рисков и просрочек за месяц')
            ->line('Сформирован перечень мероприятий, требующих решения проректора: '.count($this->needsDecision).'.');

        foreach (array_slice($this->needsDecision, 0, 20) as $m) {
            $mail->line("№{$m['number']} «{$m['title']}» — срок {$m['deadline']}, {$m['percent']}%".($m['problem'] ? " — {$m['problem']}" : ''));
        }

        return $mail;
    }
}
