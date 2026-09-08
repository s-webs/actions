<?php

namespace App\Policies;

use App\Models\User;

/**
 * Закрытие периода — роль `administrator` (после слияния бывших `coordinator` и
 * `proctor` разделение прав между ними больше не действует — [[Роли и права#Администраторы]]).
 */
class PeriodPolicy
{
    public function close(User $user): bool
    {
        return $user->hasRole('administrator');
    }
}
