<x-auth-layout title="Choose a new password">
<p class="form-intro">Use at least 8 characters. Any combination is allowed.</p>
<form method="POST" action="{{ route('password.update') }}" class="form-stack">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', request('email')) }}" required autocomplete="username"></div>
    <div><label for="password">New password</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
    <div><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
    <button class="button primary full">Save new password <x-icon name="arrow" /></button>
</form>
</x-auth-layout>


