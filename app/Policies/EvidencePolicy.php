<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\User;

/**
 * [[Функциональные требования#4.6 Модуль «Доказательная база»]]: документы удаляет
 * роль `administrator` (запись в аудит — task-019, `Auditable` на модели).
 */
class EvidencePolicy
{
    public function delete(User $user, Evidence $evidence): bool
    {
        return $user->hasRole('administrator');
    }
}
