<?php

namespace App\Policies;

use App\Models\InfoAttendee;
use App\Models\User;

class InfoAttendeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('info.access');
    }

    public function view(User $user, InfoAttendee $attendee): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('info.attendee.create');
    }

    public function update(User $user, InfoAttendee $attendee): bool
    {
        return $this->create($user);
    }
}
