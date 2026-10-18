<x-layout title="Guest detail">
    <div class="page-heading"><div><p class="eyebrow">GUEST SERVICE</p><h1>{{ $guest->full_name }}</h1><p>{{ $guest->session->event->name }} · {{ $guest->session->date_local->format('d M Y') }} · {{ $guest->session->label }}</p></div><div class="button-row"><a class="button secondary" href="{{ route('guests.index') }}">Guest records</a>@can('update', $guest)<a class="button primary" href="{{ route('guests.edit', $guest) }}">Edit details</a>@endcan</div></div>
    <section class="panel edit-panel">
        <div class="button-row"><span class="badge">{{ $guest->current_status }}</span><span class="badge">{{ $guest->activeAssignment ? 'ASSIGNED' : 'UNASSIGNED' }}</span>@if($guest->archived_at)<span class="badge">ARCHIVED</span>@endif</div>
        <dl class="profile-grid guest-profile">
            <div><dt>Phone</dt><dd><a class="text-link" href="tel:{{ $guest->phone_normalized }}">{{ $guest->phone_raw }}</a></dd></div>
            <div><dt>Assigned officer</dt><dd>{{ $guest->activeAssignment?->officer?->name ?? 'Unassigned' }}</dd></div>
            <div><dt>Gender</dt><dd>{{ $guest->gender ?: 'Not provided' }}</dd></div>
            <div><dt>Home address</dt><dd>{{ $guest->home_address ?: 'Not provided' }}</dd></div>
            <div><dt>Heard about SUMMIT</dt><dd>{{ $guest->heard_about ?: 'Not provided' }}</dd></div>
            <div><dt>Original capture time (WAT)</dt><dd>{{ $guest->captured_at_original_utc?->setTimezone('Africa/Lagos')->format('d M Y H:i') ?? 'Not provided' }}</dd></div>
            <div><dt>Entered (WAT)</dt><dd>{{ $guest->created_at->setTimezone('Africa/Lagos')->format('d M Y H:i') }}</dd></div>
            <div><dt>Entry notes</dt><dd>{{ $guest->entry_notes ?: 'None' }}</dd></div>
        </dl>
        @can('update', $guest)
            @if($guest->legacy_officer_name || $guest->raw_source_json)
                <details class="additional-details"><summary>Imported source metadata</summary><p class="muted-text">Legacy text is preserved for review. Current status and assignment are shown above.</p>
                    @if($guest->legacy_officer_name)<p>Legacy officer: {{ $guest->legacy_officer_name }}</p>@endif
                    <dl class="guest-source">@foreach($guest->raw_source_json ?? [] as $key => $value)<div><dt>{{ $key }}</dt><dd>{{ is_scalar($value) ? $value : json_encode($value) }}</dd></div>@endforeach</dl>
                </details>
            @endif
        @endcan
    </section>
    @include('guest.contact-panel')
</x-layout>
