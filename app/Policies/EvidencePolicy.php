<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\User;

/**
 * Документы удаляет administrator по всем мероприятиям или responsible по своим.
 */
class EvidencePolicy
{
    public function delete(User $user, Evidence $evidence): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (! $user->hasRole('responsible')) {
            return false;
        }

        $measure = $evidence->measure;

        return $measure !== null && $user->ownsMeasure($measure);
    }
}
