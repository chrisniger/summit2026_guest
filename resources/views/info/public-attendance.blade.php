<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Attendance · SUMMIT 2026</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-body">
<main class="public-shell">
    <section class="public-card">
        <div class="public-brand">
            <img src="{{ asset('brand/summit-logo.png') }}" alt="Summit Bible Church" width="1920" height="610">
            <div class="event-wordmark">SUMMIT <span>2026</span></div>
        </div>
        <div class="public-intro">
            <h1>Attendance</h1>
            <p>Enter your details to mark the active session present.</p>
        </div>
        @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
        @if(session('attendance_error'))<div class="notice error" role="alert">{{ session('attendance_error') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert"><strong>Please review the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($session)
            <p class="public-session"><x-icon name="calendar" /> {{ $session->label.' · '.$session->name }} · {{ $session->date_local->format('j M Y') }} · {{ $session->starts_at_utc->setTimezone('Africa/Lagos')->format('H:i').'–'.$session->ends_at_utc->setTimezone('Africa/Lagos')->format('H:i') }} WAT</p>
            <form method="POST" action="{{ route('attendance.public.store') }}" class="form-stack">
                @csrf
                <input type="hidden" name="submission_id" value="{{ old('submission_id', (string) \Illuminate\Support\Str::uuid()) }}">
                <div><label for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" maxlength="255" autocomplete="given-name" required autofocus></div>
                <div><label for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" maxlength="255" autocomplete="family-name" required></div>
                <div><label for="phone_raw">Phone number</label><input id="phone_raw" name="phone_raw" value="{{ old('phone_raw') }}" type="tel" maxlength="50" autocomplete="tel" required></div>
                <button class="button primary full" type="submit">Mark me present <x-icon name="arrow" /></button>
            </form>
        @else
            <div class="notice error" role="alert">Check-in is not open right now. Please see the Info Com desk.</div>
        @endif
    </section>
    <footer class="public-footer">SUMMIT 2026 · Summit Bible Church</footer>
</main>
</body>
</html>
