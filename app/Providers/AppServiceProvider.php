<?php

namespace App\Providers;

use App\Models\FollowUpAssignment;
use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Models\User;
use App\Policies\AttendancePolicy;
use App\Policies\FollowUpAssignmentPolicy;
use App\Policies\GuestRecordPolicy;
use App\Policies\InfoAttendeePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));
        Gate::before(function (User $user): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return $user->hasRole('super_admin') ? true : null;
        });
        foreach (array_unique(array_merge(...array_values(config('summit.permissions')))) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
        Gate::policy(InfoAttendee::class, InfoAttendeePolicy::class);
        Gate::policy(InfoAttendanceRecord::class, AttendancePolicy::class);
        Gate::policy(GuestRecord::class, GuestRecordPolicy::class);
        Gate::policy(FollowUpAssignment::class, FollowUpAssignmentPolicy::class);
        foreach (config('summit.roles') as $role => $label) {
            Gate::define('workspace.'.$role, fn (User $user): bool => $user->hasRole($role));
        }
        RateLimiter::for('passwords', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
