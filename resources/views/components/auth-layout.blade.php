@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} · SUMMIT 2026</title>
    <x-theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="auth-body">
<main class="auth-shell">
    <section class="auth-brand" aria-label="SUMMIT 2026 conference">
        <img class="auth-logo" src="{{ asset('brand/summit-logo.png') }}" alt="Summit Bible Church" width="1920" height="610">
        <div class="auth-message"><span class="eyebrow light">SUMMIT BIBLE CHURCH PRESENTS</span><h1>SUMMIT<span>2026</span></h1><div class="red-rule"></div><p>One conference.<br>Every connection matters.</p><div class="auth-date"><x-icon name="calendar" /><span>27 October — 1 November 2026<br><small>Africa/Lagos · WAT</small></span></div></div>
        <small class="auth-brand-footer">REGISTRATION · ATTENDANCE · GUEST FOLLOW-UP</small>
    </section>
    <section class="auth-form-panel">
        <div class="auth-form-inner">
            <div class="theme-toolbar"><x-theme-switch /></div>
            <span class="eyebrow">CONFERENCE OPERATIONS</span>
            <h2>{{ $title }}</h2>
            @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="notice error" role="alert" id="form-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
            <div id="connection-status" class="notice error" role="alert" tabindex="-1" hidden>Connection lost. Your entered values are still here. Reconnect before saving.</div>
            <div class="auth-security"><x-icon name="shield" /><span>Authorized staff access only.<br>Need access? Contact your Super Admin.</span></div>
        </div>
        <footer class="auth-footer">SUMMIT 2026 · Summit Bible Church</footer>
    </section>
</main>
@livewireScripts
</body>
</html>

