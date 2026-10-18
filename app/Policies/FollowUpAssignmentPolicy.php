<?php

namespace App\Policies;

use App\Models\User;

class FollowUpAssignmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('guest.assign');
    }
}
