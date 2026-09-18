<?php

namespace App\Policies;

use App\Models\StagePeriodUpdate;
use App\Models\User;

/**
 * `%` этапа и решение (утвердить/отклонить/на доработку). Administrator — все
 * мероприятия; responsible — только свои. Guard `measure` сюда не допускается.
 */
class StagePeriodUpdatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrator', 'responsible']);
    }

    public function approve(User $user, StagePeriodUpdate $stagePeriodUpdate): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if (! $user->hasRole('responsible')) {
            return false;
        }

        $measure = $stagePeriodUpdate->stage?->measure;

        return $measure !== null && $user->ownsMeasure($measure);
    }
}
