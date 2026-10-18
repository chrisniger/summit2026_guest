<x-layout title="Guest records">
    <div class="page-heading"><div><p class="eyebrow">GUEST SERVICE</p><h1>Guest records</h1><p>Browse the encounters available to your role.</p></div>
        @can('create', \App\Models\GuestRecord::class)<a class="button primary" href="{{ route('guests.create') }}"><x-icon name="edit" /> Enter guest</a>@endcan
    </div>
    <form method="GET" class="panel edit-panel form-stack" aria-label="Filter guests" x-data>
        <div class="form-grid">
            <div><label for="search">Name or phone</label><input id="search" name="search" value="{{ request('search') }}" maxlength="255" placeholder="Search Guest Service"></div>
            <div><label for="year">Event year</label><select id="year" name="year" @change="$refs.session.value = ''">@foreach($events as $event)<option value="{{ $event->year }}" @selected($year === $event->year)>SUMMIT {{ $event->year }}</option>@endforeach</select></div>
            <div><label for="session">Encounter session</label><select id="session" name="session" x-ref="session"><option value="">All sessions in selected year</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(request('session') == $session->id)>{{ $session->date_local->format('d M Y') }} · {{ $session->label }}</option>@endforeach</select></div>
            <div><label for="day">Conference day</label><input id="day" type="number" min="1" max="31" name="day" value="{{ request('day') }}"></div>
            <div><label for="status">Current status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['NOT CONTACTED', 'CONTACTED', 'UNREACHABLE'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div><label for="assignment">Assignment</label><select id="assignment" name="assignment"><option value="">All assignments</option><option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned</option><option value="assigned" @selected(request('assignment') === 'assigned')>Assigned</option></select></div>
            <div><label for="archive">Record state</label><select id="archive" name="archive"><option value="active">Active records</option><option value="archived" @selected(request('archive') === 'archived')>Archived records</option></select></div>
        </div>
        <div class="button-row"><button class="button primary">Apply filters</button><a class="button secondary" href="{{ route('guests.index') }}">Reset</a><span class="muted-text">{{ $guests->total() }} matching encounters</span></div>
    </form>
    <div class="attendee-grid">
        @forelse($guests as $guest)
            <article class="panel attendee-card">
                @can('guest.assign')<label class="checkbox-row"><input form="bulk-assign" type="checkbox" name="guest_ids[]" value="{{ $guest->id }}" aria-label="Select {{ $guest->full_name }} for assignment"> Select encounter #{{ $guest->id }}</label>@endcan
                <div class="card-title"><div><h2><a href="{{ route('guests.show', $guest) }}">{{ $guest->full_name }}</a></h2><p>{{ $guest->phone_raw }}</p></div><span class="badge">{{ $guest->current_status }}</span></div>
                <p class="muted-text">{{ $guest->session->date_local->format('d M Y') }} · {{ $guest->session->label }}</p>
                <p class="muted-text">{{ $guest->activeAssignment ? 'Assigned to '.$guest->activeAssignment->officer->name : 'UNASSIGNED' }}</p>
                <div class="button-row"><a class="button secondary small" href="{{ route('guests.show', $guest) }}">View guest</a>@can('update', $guest)<a class="button secondary small" href="{{ route('guests.edit', $guest) }}">Edit details</a>@endcan</div>
            </article>
        @empty
            <div class="panel empty-state"><x-icon name="people" /><strong>No guests match these filters</strong><p>Try another name, phone, session or event year.</p></div>
        @endforelse
    </div>
    <div class="pagination">{{ $guests->links() }}</div>
    @can('guest.assign')
        <form id="bulk-assign" method="POST" action="{{ route('follow-up.assignments.preview') }}" class="panel edit-panel form-stack">
            @csrf<h2>Manage selected assignments</h2><p class="muted-text">Select up to 200 encounters. The next screen shows the exact selection and changes for confirmation.</p>
            <div class="form-grid">
                <div><label for="assignment_action">Action *</label><select id="assignment_action" name="action" required><option value="assign">Assign unassigned guests</option><option value="reassign">Reassign selected guests</option><option value="unassign">Unassign selected guests</option></select></div>
                <div><label for="officer_user_id">New officer (except unassign)</label><select id="officer_user_id" name="officer_user_id"><option value="">Choose an active officer</option>@foreach($officers as $officer)<option value="{{ $officer->id }}">{{ $officer->name }}</option>@endforeach</select></div>
                <div><label for="assignment_reason">Reason *</label><textarea id="assignment_reason" name="reason" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea></div>
            </div><button class="button primary">Review assignment changes</button>
        </form>
    @endcan
</x-layout>
