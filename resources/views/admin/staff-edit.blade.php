<x-layout title="Edit staff account">
<div class="page-heading"><div><span class="eyebrow">ADMINISTRATION · STAFF</span><h1>Edit account</h1><p>Update {{ $staff->name }}’s display name, login email, or password.</p></div><a class="button secondary" href="{{ route('admin.staff') }}">Back to staff</a></div>
<section class="panel edit-panel">
    <form method="POST" action="{{ route('admin.staff.account.update', $staff) }}" class="form-stack">
        @csrf
        @method('PATCH')
        <div class="form-grid">
            <div><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $staff->name) }}" required maxlength="255" autocomplete="name"></div>
            <div><label for="email">Login email (username)</label><input id="email" name="email" type="email" value="{{ old('email', $staff->email) }}" required maxlength="255" autocomplete="username"></div>
            <div><label for="password">New password <span class="muted-text">(leave blank to keep the current password)</span></label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" aria-describedby="password-hint"><small id="password-hint">Use a strong temporary password and share it privately.</small></div>
            <div><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password"></div>
            @if($staff->is(auth()->user()))
                <div><label for="current_password">Your current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"><small>Confirm your password before saving changes to your own account.</small></div>
            @endif
        </div>
        @if($staff->is(auth()->user()))
            <p class="muted-text">Changing your login email or password signs out your other sessions. This session stays active.</p>
        @else
            <p class="muted-text">Changing this staff member’s login email or password signs out their active sessions.</p>
        @endif
        <div class="button-row"><button class="button primary">Save account</button><a class="button secondary" href="{{ route('admin.staff') }}">Cancel</a></div>
    </form>
</section>
</x-layout>
