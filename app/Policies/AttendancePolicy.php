<?php

namespace App\Policies;

use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('info.access');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('info.attendance.mark');
    }

    public function correct(User $user): bool
    {
        return $user->hasPermission('platform.manage');
    }
}
