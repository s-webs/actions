<?php

namespace App\Policies;

use App\Models\Responsible;
use App\Models\User;

class ResponsiblePolicy
{
    /** Свод по всем ответственным — не для роли `responsible`. */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAllMeasures();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    public function update(User $user, Responsible $responsible): bool
    {
        return $user->hasRole('administrator');
    }
}
