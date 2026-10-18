<div wire:poll.30s>
<div class="session-banner"><div class="banner-icon"><x-icon name="calendar" /></div><div><strong>{{ $session ? $session->label.' · '.$session->name : 'No active conference session' }}</strong><p>{{ $session ? $session->date_local->format('j F Y').' · '.$session->starts_at_utc->setTimezone('Africa/Lagos')->format('H:i').'–'.$session->ends_at_utc->setTimezone('Africa/Lagos')->format('H:i').' WAT' : 'You can register and search attendees. Check-in opens only during an enabled session.' }}</p></div>@can('platform.manage')<a class="button secondary" href="{{ route('admin.sessions') }}">Session settings</a>@endcan</div>
@if($message)<div class="notice success" role="status">{{ $message }}</div>@endif
@error('check_in')<div class="notice error" role="alert">{{ $message }}</div>@enderror
<section class="panel edit-panel" style="margin-top:0"><label for="attendee-search">Search by name or phone</label><input id="attendee-search" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="Name, 080…, 234… or +234…" autocomplete="off" autofocus x-on:check-in-saved.window="$el.focus()"><p class="muted-text" style="margin-top:8px">Shared phone numbers may return multiple people. Confirm the name before checking in.</p></section>
<div class="attendee-grid" wire:loading.class="is-loading">
@forelse($attendees as $attendee)
<article @class(['panel','attendee-card','is-selected'=>$selected===$attendee->id]) wire:key="attendee-{{ $attendee->id }}">
<div class="card-title"><div><h2>{{ $attendee->first_name }} {{ $attendee->last_name }}</h2><p>{{ $attendee->phone_raw }}</p></div><span class="badge {{ $attendee->attendanceRecords->isNotEmpty() ? 'success':'muted' }}">{{ $attendee->attendanceRecords->isNotEmpty() ? 'Present' : 'Not checked in' }}</span></div>
<p class="muted-text">Attendee #{{ $attendee->id }} · {{ $attendee->attendance_preference ?: 'Preference not specified' }}</p>
<div class="button-row"><a class="button secondary small" href="{{ route('info.attendees.show',$attendee) }}">View record</a>
@if($selected===$attendee->id)
    @if($session)<button class="button primary" wire:click="mark({{ $session->id }})" wire:loading.attr="disabled">Confirm present</button>@else<span class="muted-text">Check-in is closed.</span>@endif
@else<button class="button secondary small" wire:click="selectAttendee({{ $attendee->id }})">Select attendee</button>@endif
</div></article>
@empty<section class="panel empty-state"><x-icon name="people" /><strong>No attendees found</strong><p>Try another name or phone number, or register a new attendee.</p></section>@endforelse
</div><div class="pagination">{{ $attendees->links() }}</div><span wire:loading role="status" class="muted-text">Updating…</span>
</div>

