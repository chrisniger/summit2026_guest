<x-auth-layout title="Reset your password">
<p class="form-intro">Enter your staff email address to request a reset link.</p>
<form method="POST" action="{{ route('password.email') }}" class="form-stack">@csrf
    <div><label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></div>
    <button class="button primary full">Send reset link <x-icon name="arrow" /></button>
    <a class="text-link" href="{{ route('login') }}">Back to sign in</a>
</form>
</x-auth-layout>

