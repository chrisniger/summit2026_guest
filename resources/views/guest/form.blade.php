<x-layout :title="$guest ? 'Edit guest' : 'Enter guest'">
    <div class="page-heading"><div><p class="eyebrow">GUEST SERVICE</p><h1>{{ $guest ? 'Edit guest details' : 'Enter a guest' }}</h1><p>Select the session where this encounter happened, even when entering it later.</p></div><a class="button secondary" href="{{ route('guests.index') }}">Guest records</a></div>
    @if($candidates->isNotEmpty())
        <section class="notice" aria-label="Possible duplicates"><strong>Review possible matching encounters</strong><ul class="candidate-list">@foreach($candidates as $candidate)<li><a class="text-link" href="{{ route('guests.show', $candidate) }}">{{ $candidate->full_name }} · {{ $candidate->phone_raw }} · {{ $candidate->session->label }} · {{ $candidate->session->date_local->format('d M Y') }}</a></li>@endforeach</ul><p>Check these records before confirming another encounter below.</p></section>
    @endif
    <form method="POST" action="{{ $guest ? route('guests.update', $guest) : route('guests.store') }}" class="panel edit-panel form-stack">
        @csrf @if($guest) @method('PATCH')<input type="hidden" name="version" value="{{ old('version', $guest->version) }}">@endif
        <input type="hidden" name="submission_id" value="{{ old('submission_id', $submissionId) }}">
        <div class="form-grid">
            <div><label for="conference_session_id">Actual encounter session *</label><select id="conference_session_id" name="conference_session_id" required><option value="">Select a session</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(old('conference_session_id', $selectedSession) == $session->id)>{{ $session->event->year }} · {{ $session->date_local->format('d M') }} · {{ $session->label }}</option>@endforeach</select><small>This session stays selected when you save and add another.</small></div>
            <div><label for="full_name">Full name *</label><input id="full_name" name="full_name" required maxlength="255" autocomplete="name" value="{{ old('full_name', $guest?->full_name) }}" autofocus></div>
            <div><label for="phone_raw">Phone *</label><input id="phone_raw" name="phone_raw" type="tel" required maxlength="50" autocomplete="tel" value="{{ old('phone_raw', $guest?->phone_raw) }}"></div>
            <div><label for="gender">Gender</label><input id="gender" name="gender" maxlength="255" value="{{ old('gender', $guest?->gender) }}"></div>
        </div>
        <details class="additional-details" @if($guest || $errors->any()) open @endif><summary>Additional guest details</summary><div class="form-grid">
            <div><label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="255" value="{{ old('first_name', $guest?->first_name) }}"></div>
            <div><label for="last_name">Last name</label><input id="last_name" name="last_name" maxlength="255" value="{{ old('last_name', $guest?->last_name) }}"></div>
            <div><label for="home_address">Home address</label><textarea id="home_address" name="home_address" maxlength="5000">{{ old('home_address', $guest?->home_address) }}</textarea></div>
            <div><label for="heard_about">How did they hear about SUMMIT?</label><input id="heard_about" name="heard_about" list="referrals" maxlength="255" value="{{ old('heard_about', $guest?->heard_about) }}"><datalist id="referrals">@foreach(config('summit.referral_options') as $option)<option value="{{ $option }}">@endforeach</datalist></div>
            <div><label for="captured_at_original_utc">Original capture timestamp</label><input id="captured_at_original_utc" name="captured_at_original_utc" placeholder="2025-10-31T09:30:00+01:00" value="{{ old('captured_at_original_utc', $guest?->captured_at_original_utc?->format('Y-m-d\TH:i:sP')) }}"><small>Optional ISO timestamp with timezone. Kept separately from the encounter session.</small></div>
            <div><label for="entry_notes">Entry notes</label><textarea id="entry_notes" name="entry_notes" maxlength="5000">{{ old('entry_notes', $guest?->entry_notes) }}</textarea></div>
        </div></details>
        @if($candidates->isNotEmpty() || $errors->has('keep_separate'))
            <label class="checkbox-row"><input type="checkbox" name="keep_separate" value="1" @checked(old('keep_separate'))> I reviewed the matching records. Keep this as a separate encounter.</label>
        @endif
        <div class="button-row"><button class="button primary">{{ $guest ? 'Save changes' : 'Save guest' }}</button>@unless($guest)<button class="button secondary" name="add_another" value="1">Save & add another</button>@endunless</div>
    </form>
</x-layout>
