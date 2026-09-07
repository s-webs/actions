<?php

namespace App\Enums;

/**
 * Жизненный цикл этапа за период — [[Заполнение и утверждение#Жизненный цикл этапа за период]].
 */
enum ReviewState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Rework = 'rework';
}
