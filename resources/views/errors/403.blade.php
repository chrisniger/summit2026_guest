@auth
<x-layout title="Access denied"><section class="panel empty-state"><div class="domain-icon red"><x-icon name="shield" /></div><span class="eyebrow">403 · ACCESS DENIED</span><h1>This workspace is restricted</h1><p>Your account does not have permission to access this page. Contact your Super Admin if you need access.</p>@if(auth()->user()->workspace() !== 'access-denied')<a href="{{ route('dashboard') }}" class="button primary">Return to your workspace <x-icon name="arrow" /></a>@endif</section></x-layout>
@else
<x-auth-layout title="Access denied"><p class="form-intro">Sign in with an authorized staff account to continue.</p><a class="button primary" href="{{ route('login') }}">Sign in</a></x-auth-layout>
@endauth

