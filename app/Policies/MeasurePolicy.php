<?php

namespace App\Policies;

use App\Models\User;

/**
 * Импорт (первичный и повторный) — координатор наполняет план; проректор может
 * выполнить любое действие координатора — [[Роли и права#Администраторы]].
 */
class MeasurePolicy
{
    public function import(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'proctor']);
    }

    /** Учётные данные мероприятий — [[Функциональные требования#4.14]]. */
    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'proctor']);
    }
}
