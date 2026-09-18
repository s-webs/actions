<?php

namespace App\Policies;

use App\Http\Controllers\Measure\WorkspaceController;
use App\Models\Measure;
use App\Models\User;

/**
 * Импорт и наполнение плана — роль `administrator`. Заведение этапов доступно
 * и с логина мероприятия ({@see WorkspaceController}). `manage` — учётные данные:
 * administrator видит все, responsible — только свои. Структурный оверрайд
 * этапов и «принять работу» — `override` / `accept`, только administrator.
 */
class MeasurePolicy
{
    public function view(User $user, Measure $measure): bool
    {
        return $user->canAccessAllMeasures() || $user->ownsMeasure($measure);
    }

    public function import(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    /** Ручное создание мероприятия через UI — та же роль, что и импорт плана. */
    public function create(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    /**
     * Учётные данные мероприятий. На классе — вход в модуль; на экземпляре —
     * действие по конкретному мероприятию.
     */
    public function manage(User $user, mixed $measure = null): bool
    {
        if (! $user->hasAnyRole(['administrator', 'responsible'])) {
            return false;
        }

        if ($measure instanceof Measure) {
            return $user->hasRole('administrator') || $user->ownsMeasure($measure);
        }

        return true;
    }

    /** Структурный оверрайд этапов в реестре плана. */
    public function override(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    /** «Принять работу» — 100% после утверждения всех этапов. */
    public function accept(User $user): bool
    {
        return $user->hasRole('administrator');
    }
}
