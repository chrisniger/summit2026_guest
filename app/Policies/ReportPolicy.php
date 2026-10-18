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
        return $user->hasPermission('guest.report.view');
    }
}
