<?php

namespace App\Policies;

use App\Models\Direction;
use App\Models\User;

class DirectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    public function update(User $user, Direction $direction): bool
    {
        return $user->hasRole('administrator');
    }

    public function delete(User $user, Direction $direction): bool
    {
        return $user->hasRole('administrator');
    }
}
