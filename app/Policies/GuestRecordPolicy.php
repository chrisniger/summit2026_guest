<?php

namespace App\Policies;

use App\Models\GuestRecord;
use App\Models\User;

class GuestRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('guest.access');
    }

    public function view(User $user, GuestRecord $guest): bool
    {
        return GuestRecord::visibleTo($user)->whereKey($guest->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('guest.create');
    }

    public function update(User $user, GuestRecord $guest): bool
    {
        return $this->create($user) && $this->view($user, $guest);
    }

    public function contact(User $user, GuestRecord $guest): bool
    {
        return $user->hasPermission('guest.contact') && $this->view($user, $guest);
    }
}
