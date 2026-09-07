<?php

namespace App\Enums;

/**
 * Этап 2 — «Рабочая группа: решения и поручения» ([[Функциональные требования#4.8]]).
 */
enum AssignmentStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Overdue = 'overdue';
}
