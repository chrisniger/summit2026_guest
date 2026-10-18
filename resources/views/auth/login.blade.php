<x-auth-layout title="Welcome back">
<p class="form-intro">Sign in to your conference workspace.</p>
<form method="POST" action="{{ route('login.store') }}" class="form-stack">
    @csrf
    <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus maxlength="255" @if($errors->has('email')) aria-invalid="true" aria-describedby="form-errors" @endif placeholder="you@example.com"></div>
    <div x-data="{ visible: false }"><div class="label-row"><label for="password">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div><div class="password-field"><input id="password" :type="visible ? 'text' : 'password'" type="password" name="password" autocomplete="current-password" required><button type="button" @click="visible = !visible" :aria-pressed="visible" x-text="visible ? 'Hide' : 'Show'">Show</button></div></div>
    <button class="button primary full" type="submit">Sign in to workspace <x-icon name="arrow" /></button>
</form>
</x-auth-layout>

