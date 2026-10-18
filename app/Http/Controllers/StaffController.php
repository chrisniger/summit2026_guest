<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff', ['users' => User::with('roles')->orderBy('name')->paginate(20)]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->only('name', 'email', 'password'));
            $user->roles()->attach(Role::where('name', $request->validated('role'))->firstOrFail());
            app(Audit::class)->record('platform', 'staff.created', $user, $request->user(), [], ['role' => $request->validated('role'), 'is_active' => true]);
        });

        return back()->with('status', 'Staff account created.');
    }

    public function update(StaffRequest $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['staff' => 'Ask another Super Admin to change your own role or account status.']);
        }
        DB::transaction(function () use ($request, $user) {
            $adminRole = Role::where('name', 'super_admin')->lockForUpdate()->firstOrFail();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasRole('super_admin') && (! $request->boolean('is_active') || $request->validated('role') !== 'super_admin')) {
                $other = User::where('is_active', true)->whereKeyNot($user->id)->whereHas('roles', fn ($q) => $q->whereKey($adminRole->id))->exists();
                if (! $other) {
                    throw ValidationException::withMessages(['staff' => 'Keep at least one active Super Admin.']);
                }
            }
            $before = ['role' => $user->roles->pluck('name')->implode(','), 'is_active' => $user->is_active];
            $user->forceFill(['is_active' => $request->boolean('is_active')])->save();
            $user->roles()->sync([Role::where('name', $request->validated('role'))->firstOrFail()->id]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            app(Audit::class)->record('platform', 'staff.updated', $user, $request->user(), $before, ['role' => $request->validated('role'), 'is_active' => $user->is_active]);
        });

        return back()->with('status', 'Staff access updated; existing sessions were revoked.');
    }
}
