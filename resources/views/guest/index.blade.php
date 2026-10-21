<x-layout title="Guest records">
    <div class="page-heading"><div><p class="eyebrow">GUEST SERVICE</p><h1>Guest records</h1><p>Browse the encounters available to your role.</p></div>
        @can('create', \App\Models\GuestRecord::class)<a class="button primary" href="{{ route('guests.create') }}"><x-icon name="edit" /> Enter guest</a>@endcan
    </div>
    <details class="panel filter-panel">
        <summary class="filter-panel-summary"><x-icon name="filter"/><span><strong>Filter guest records</strong><small>Search and narrow the guest list · {{ $guests->total() }} matching encounters</small></span><span class="filter-panel-control"><span class="filter-open-label">Show filters</span><span class="filter-close-label">Hide filters</span><x-icon name="chevron"/></span></summary>
        <form method="GET" class="filter-panel-content form-stack" aria-label="Filter guests" x-data>
            <div class="form-grid">
                <div><label for="search">Name or phone</label><input id="search" name="search" value="{{ request('search') }}" maxlength="255" placeholder="Search Guest Service"></div>
                <div><label for="year">Event year</label><select id="year" name="year" @change="$refs.session.value = ''">@foreach($events as $event)<option value="{{ $event->year }}" @selected($year === $event->year)>SUMMIT {{ $event->year }}</option>@endforeach</select></div>
                <div><label for="session">Encounter session</label><select id="session" name="session" x-ref="session"><option value="">All sessions in selected year</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(request('session') == $session->id)>{{ $session->date_local->format('d M Y') }} · {{ $session->label }}</option>@endforeach</select></div>
                <div><label for="status">Current status</label><select id="status" name="status"><option value="">All statuses</option>@foreach(['NOT CONTACTED', 'CONTACTED', 'UNREACHABLE', 'WRONG NUMBER'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
                <div><label for="per_page">Guests per page</label><select id="per_page" name="per_page">@foreach([25, 50, 100, 200] as $pageSize)<option value="{{ $pageSize }}" @selected($guests->perPage() === $pageSize)>{{ $pageSize }}</option>@endforeach</select></div>
            </div>
            <details class="additional-details">
                <summary>Advanced filters @if(request('assignment') || request('archive') === 'archived')<span class="badge muted" style="margin-left:12px">Filters active</span>@endif</summary>
                <div class="form-grid">
                    <div><label for="assignment">Assignment</label><select id="assignment" name="assignment"><option value="">All assignments</option><option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned</option><option value="assigned" @selected(request('assignment') === 'assigned')>Assigned</option></select></div>
                    <div><label for="archive">Record state</label><select id="archive" name="archive"><option value="active">Active records</option><option value="archived" @selected(request('archive') === 'archived')>Archived records</option></select></div>
                </div>
            </details>
            <div class="button-row"><button class="button primary"><x-icon name="filter"/>Apply filters</button><a class="button secondary" href="{{ route('guests.index') }}">Reset</a><span class="muted-text">Showing {{ $guests->firstItem() ?? 0 }}–{{ $guests->lastItem() ?? 0 }} of {{ $guests->total() }} matching encounters · Page {{ $guests->currentPage() }} of {{ $guests->lastPage() }}</span></div>
        </form>
    </details>
    @if($guests->hasPages())<div class="pagination">{{ $guests->links() }}</div>@endif
    <div class="attendee-grid">
        @forelse($guests as $guest)
            <article class="panel attendee-card">
                @can('guest.assign')<label class="checkbox-row"><input form="bulk-assign" type="checkbox" name="guest_ids[]" value="{{ $guest->id }}" aria-label="Select {{ $guest->full_name }} for assignment"> Select encounter #{{ $guest->id }}</label>@endcan
                <div class="card-title"><div><h2><a href="{{ route('guests.show', $guest) }}">{{ $guest->full_name }}</a></h2>@php($dialablePhone = $guest->phone_normalized ?: \App\Services\PhoneNumber::normalize($guest->phone_raw))@if($dialablePhone)<p><a class="guest-phone-link" href="tel:{{ $dialablePhone }}" aria-label="Call {{ $guest->full_name }} at {{ $guest->phone_raw }}"><x-icon name="phone"/>{{ $guest->phone_raw }}</a></p>@else<p class="guest-phone-text"><x-icon name="phone"/>{{ $guest->phone_raw }}</p>@endif</div><span class="badge">{{ $guest->current_status }}</span></div>
                <p class="muted-text">{{ $guest->session->date_local->format('d M Y') }} · {{ $guest->session->label }}</p>
                <p class="muted-text">{{ $guest->activeAssignment ? 'Assigned to '.$guest->activeAssignment->officer->name : 'UNASSIGNED' }}</p>
                @can('contact', $guest)
                    @unless($guest->archived_at)
                        @php($quickOld = (string) old('quick_guest_id') === (string) $guest->id)
                        <details class="quick-outcome additional-details" @if($quickOld) open @endif>
                            <summary><x-icon name="edit"/>Quick follow-up update</summary>
                            <form method="POST" action="{{ route('follow-up.activities.store', $guest) }}{{ request()->getQueryString() ? '?'.request()->getQueryString() : '' }}" class="quick-contact-form" x-data="{ outcome: @js($quickOld ? old('outcome', '') : '') }">
                                @csrf
                                <input type="hidden" name="submission_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="version" value="{{ $guest->version }}">
                                <input type="hidden" name="assignment_id" value="{{ $guest->activeAssignment?->id }}">
                                <input type="hidden" name="return_to" value="list">
                                <input type="hidden" name="quick_guest_id" value="{{ $guest->id }}">
                                <div class="form-grid">
                                    <div><label for="quick-method-{{ $guest->id }}">Method</label><select id="quick-method-{{ $guest->id }}" name="method" required>@foreach(['call' => 'Call', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'visit' => 'Visit', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(($quickOld ? old('method', 'call') : 'call') === $value)>{{ $label }}</option>@endforeach</select></div>
                                    <div><label for="quick-outcome-{{ $guest->id }}">Outcome</label><select id="quick-outcome-{{ $guest->id }}" name="outcome" x-model="outcome" required><option value="">Choose an outcome</option><option value="NOTE_ONLY">Note only — keep status</option><option value="CONTACTED">Contacted</option><option value="UNREACHABLE">Unreachable</option><option value="WRONG_NUMBER">Wrong number</option></select></div>
                                    <div><label for="quick-time-{{ $guest->id }}">Attempt time (WAT)</label><input id="quick-time-{{ $guest->id }}" name="attempted_at" type="datetime-local" value="{{ $quickOld ? old('attempted_at', now()->setTimezone('Africa/Lagos')->format('Y-m-d\\TH:i')) : now()->setTimezone('Africa/Lagos')->format('Y-m-d\\TH:i') }}" required></div>
                                    <div><label for="quick-comment-{{ $guest->id }}">Note (optional)</label><textarea id="quick-comment-{{ $guest->id }}" name="comment" maxlength="5000" placeholder="Briefly record what happened">{{ $quickOld ? old('comment') : '' }}</textarea></div>
                                </div>
                                <label class="checkbox-row" x-show="outcome === 'CONTACTED'" x-cloak><input type="checkbox" name="two_way_confirmed" value="1" @checked($quickOld && old('two_way_confirmed'))>I spoke with the guest (two-way contact).</label>
                                <label class="checkbox-row" x-show="outcome === 'UNREACHABLE'" x-cloak><input type="checkbox" name="failed_attempt_confirmed" value="1" @checked($quickOld && old('failed_attempt_confirmed'))>I made an attempt but could not reach the guest.</label>
                                <label class="checkbox-row" x-show="outcome === 'WRONG_NUMBER'" x-cloak><input type="checkbox" name="wrong_number_confirmed" value="1" @checked($quickOld && old('wrong_number_confirmed'))>I verified that this is the wrong number.</label>
                                <button class="button primary small">Save outcome</button>
                            </form>
                        </details>
                    @endunless
                @endcan
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
            </div><button class="button primary">Review assignment changes</button>
        </form>
    @endcan
</x-layout>
