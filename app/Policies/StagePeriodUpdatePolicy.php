<?php

namespace App\Policies;

use App\Models\StagePeriodUpdate;
use App\Models\User;

/**
 * `%` этапа и решение (утвердить/отклонить/на доработку) — единственная точка входа
 * процента в систему, только у проректора. Guard `measure` сюда не допускается вообще —
 * [[Бизнес-правила#Правило 2а · `%` появляется только через утверждение проректором]].
 */
class StagePeriodUpdatePolicy
{
    public function approve(User $user, StagePeriodUpdate $stagePeriodUpdate): bool
    {
        return $user->hasRole('proctor');
    }
}
