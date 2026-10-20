<x-layout :title="$guest ? 'Edit guest' : 'Enter guest'">
    <div class="page-heading"><div><p class="eyebrow">GUEST SERVICE</p><h1>{{ $guest ? 'Edit guest details' : 'Enter a guest' }}</h1><p>Enter the guest's details and the date of the encounter.</p></div><a class="button secondary" href="{{ route('guests.index') }}">Guest records</a></div>
    @if($candidates->isNotEmpty())
        <section class="notice" aria-label="Possible duplicates"><strong>Review possible matching encounters</strong><ul class="candidate-list">@foreach($candidates as $candidate)<li><a class="text-link" href="{{ route('guests.show', $candidate) }}">{{ $candidate->full_name }} · {{ $candidate->phone_raw }} · {{ $candidate->session->label }} · {{ $candidate->session->date_local->format('d M Y') }}</a></li>@endforeach</ul><p>Check these records before confirming another encounter below.</p></section>
    @endif
    <form method="POST" action="{{ $guest ? route('guests.update', $guest) : route('guests.store') }}" class="panel edit-panel form-stack">
        @csrf @if($guest) @method('PATCH')<input type="hidden" name="version" value="{{ old('version', $guest->version) }}">@endif
        <input type="hidden" name="submission_id" value="{{ old('submission_id', $submissionId) }}">
        <div class="form-grid">
            <div><label for="conference_session_id">Date *</label><select id="conference_session_id" name="conference_session_id" required><option value="">Select a date</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(old('conference_session_id', $selectedSession) == $session->id)>{{ $session->date_local->format('d M Y') }} · {{ $session->label }}</option>@endforeach</select><small>Choose the encounter date and session. This selection stays when you save and add another.</small></div>
            <div><label for="full_name">Full name *</label><input id="full_name" name="full_name" required maxlength="255" autocomplete="name" value="{{ old('full_name', $guest?->full_name) }}" autofocus></div>
            <div><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Select gender</option>@php($gender = old('gender', $guest?->gender))@if($gender && !in_array($gender,['Male','Female','Prefer not to say'],true))<option value="{{ $gender }}" selected>{{ $gender }}</option>@endif @foreach(['Male','Female','Prefer not to say'] as $option)<option value="{{ $option }}" @selected($gender===$option)>{{ $option }}</option>@endforeach</select></div>
            <div><label for="phone_raw">Phone number *</label><input id="phone_raw" name="phone_raw" type="tel" required maxlength="50" autocomplete="tel" value="{{ old('phone_raw', $guest?->phone_raw) }}"></div>
            <div><label for="home_address">Home address</label><textarea id="home_address" name="home_address" maxlength="5000" autocomplete="street-address" rows="3">{{ old('home_address', $guest?->home_address) }}</textarea></div>
        </div>
        @php($referrals = old('heard_about', $guest?->heard_about))
        @php($selectedReferrals = is_string($referrals) && $referrals !== '' ? [$referrals] : [])
        @php($referralOptions = array_unique(array_merge(['Social Media', 'Church Announcement', 'Personal Invitation', 'Billboard', 'Flyers'], $selectedReferrals)))
        <fieldset class="guest-referrals"><legend>How did you hear about the conference?</legend>
            <input type="hidden" name="heard_about" value="">
            <div class="referral-options">@foreach($referralOptions as $option)<label class="checkbox-row"><input type="radio" name="heard_about" value="{{ $option }}" @checked(in_array($option, $selectedReferrals, true))> {{ $option }}</label>@endforeach</div>
        </fieldset>
        <details class="additional-details" @if($guest || $errors->any()) open @endif><summary>Additional guest details</summary><div class="form-grid">
            <div><label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="255" value="{{ old('first_name', $guest?->first_name) }}"></div>
            <div><label for="last_name">Last name</label><input id="last_name" name="last_name" maxlength="255" value="{{ old('last_name', $guest?->last_name) }}"></div>
            <div><label for="captured_at_original_utc">Original capture timestamp</label><input id="captured_at_original_utc" name="captured_at_original_utc" placeholder="2025-10-31T09:30:00+01:00" value="{{ old('captured_at_original_utc', $guest?->captured_at_original_utc?->format('Y-m-d\TH:i:sP')) }}"><small>Optional ISO timestamp with timezone. Kept separately from the encounter session.</small></div>
            <div><label for="entry_notes">Entry notes</label><textarea id="entry_notes" name="entry_notes" maxlength="5000">{{ old('entry_notes', $guest?->entry_notes) }}</textarea></div>
        </div></details>
        @if($candidates->isNotEmpty() || $errors->has('keep_separate'))
            <label class="checkbox-row"><input type="checkbox" name="keep_separate" value="1" @checked(old('keep_separate'))> I reviewed the matching records. Keep this as a separate encounter.</label>
        @endif
        <div class="button-row"><button class="button primary">{{ $guest ? 'Save changes' : 'Save guest' }}</button>@unless($guest)<span class="muted-text">Saving opens a fresh form for the next guest.</span>@endunless</div>
    </form>
    @unless($guest)
        <section class="panel edit-panel" aria-labelledby="recent-entries-heading">
            <h2 id="recent-entries-heading">Your last 3 entries</h2>
            <p class="muted-text">Newest first. Your saved entries remain here when you return, so you can see where you left off.</p>
            <ul class="breakdown-list">
                @forelse($recentEntries as $entry)
                    <li><span><a class="text-link" href="{{ route('guests.show', $entry) }}"><strong>{{ $entry->full_name }}</strong></a><br>{{ $entry->phone_raw }}<br><span class="muted-text">Encounter: {{ $entry->session->date_local->format('d M Y') }} · {{ $entry->session->label }}<br>Saved {{ $entry->created_at->setTimezone('Africa/Lagos')->format('d M Y, H:i') }} WAT</span></span><span class="badge muted">#{{ $entry->id }}</span></li>
                @empty
                    <li class="muted-text">You have not entered any guests yet.</li>
                @endforelse
            </ul>
        </section>
    @endunless
</x-layout>
