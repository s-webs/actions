<?php

namespace App\Policies;

use App\Http\Controllers\Measure\WorkspaceController;
use App\Models\User;

/**
 * Импорт и наполнение плана — роль `administrator` (слияние бывших `coordinator` и
 * `proctor` — [[Роли и права#Администраторы]]). Заведение самих этапов теперь доступно
 * и с логина мероприятия ({@see WorkspaceController}) —
 * это Policy покрывает только веб-администраторскую сторону (импорт, учётные данные,
 * структурный оверрайд).
 */
class MeasurePolicy
{
    public function import(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    /** Учётные данные мероприятий, структурный оверрайд этапов — [[Функциональные требования#4.14]]. */
    public function manage(User $user): bool
    {
        return $user->hasRole('administrator');
    }
}
