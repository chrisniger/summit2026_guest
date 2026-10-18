<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        DB::transaction(function () use ($request) {
            $request->user()->forceFill(['last_login_at' => now()])->save();
            app(Audit::class)->record('platform', 'auth.login', $request->user(), $request->user());
        });

        return redirect()->intended(route($request->user()->workspace()));
    }

    public function logout(Request $request): RedirectResponse
    {
        app(Audit::class)->record('platform', 'auth.logout', $request->user(), $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function emailReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => Str::lower(trim($request->input('email'))), 'is_active' => true]);

        return back()->with('status', 'If an active account exists, a password reset link will be sent.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $status = Password::reset([...$data, 'is_active' => true], function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                app(Audit::class)->record('platform', 'auth.password_reset', $user, $user);
            });
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }
}
