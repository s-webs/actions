<?php

namespace App\Policies;

use App\Models\User;

/**
 * Через UI учёток можно создавать и править только роль `responsible`.
 */
class UserPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    public function update(User $actor, User $account): bool
    {
        return $actor->hasRole('administrator') && $account->hasRole('responsible');
    }
}
