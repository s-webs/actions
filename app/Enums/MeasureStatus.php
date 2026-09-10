<?php

namespace App\Enums;

/**
 * Статус мероприятия за период — [[Функциональные требования#4.1.1 Статусы]].
 */
enum MeasureStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case AtRisk = 'at_risk';
    case Overdue = 'overdue';
    case Done = 'done';

    public function label(): string
    {
        return __('enums.measure_status.'.$this->value);
    }
}
