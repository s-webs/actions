<?php

namespace App\Policies;

use App\Models\StagePeriodUpdate;
use App\Models\User;

/**
 * `%` этапа и решение (утвердить/отклонить/на доработку) — единственная точка входа
 * процента в систему, роль `administrator`. Guard `measure` сюда не допускается вообще —
 * [[Бизнес-правила#Правило 2а · `%` появляется только через утверждение проректором]].
 */
class StagePeriodUpdatePolicy
{
    /** Очередь «Этапы на проверку» — [[Функциональные требования#4.13]]. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    public function approve(User $user, StagePeriodUpdate $stagePeriodUpdate): bool
    {
        return $user->hasRole('administrator');
    }
}
