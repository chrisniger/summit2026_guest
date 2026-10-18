<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function info(User $user): bool
    {
        return $user->hasPermission('info.report.view');
    }

    public function followUp(User $user): bool
    {
        return $user->hasPermission('guest.access') && ($user->hasPermission('guest.report.view')
            || ($user->hasRole('officer') && $user->hasPermission('guest.contact')));
    }
}
