<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\User;

/**
 * [[Функциональные требования#4.6 Модуль «Доказательная база»]]: после закрытия периода
 * документы этого периода не удаляются, кроме как координатором (с записью в аудит —
 * task-019 добавит `Auditable` на модель). Проректор может выполнить любое действие
 * координатора — [[Роли и права#Администраторы]].
 */
class EvidencePolicy
{
    public function delete(User $user, Evidence $evidence): bool
    {
        return $user->hasAnyRole(['coordinator', 'proctor']);
    }
}
