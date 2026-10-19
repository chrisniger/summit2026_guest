<div>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="panel"><div class="section-heading"><div><h2>Session schedule</h2><p class="muted-text">No times are assumed. Enabled windows cannot overlap. End times are exclusive. Test sessions are labelled and can be removed while unused.</p></div><span class="badge outline">UTC +01:00</span></div>
<div class="table-wrap"><table><thead><tr><th>Conference session</th><th>Local date</th><th>WAT window</th><th>UTC window</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
@foreach($sessions as $session)
<tr wire:key="session-{{ $session->id }}"><td><strong>{{ $session->label }}</strong>@if($session->isTest())<span class="badge">Test</span>@endif<small>{{ $session->name }}</small></td><td>{{ $session->date_local->format('D, j M Y') }}</td><td>{{ $session->starts_at_utc ? $session->starts_at_utc->setTimezone(config('summit.timezone'))->format('H:i').' – '.$session->ends_at_utc->setTimezone(config('summit.timezone'))->format('H:i') : 'Not configured' }}</td><td>{{ $session->starts_at_utc ? $session->starts_at_utc->format('H:i').' – '.$session->ends_at_utc->format('H:i') : '—' }}</td><td><span @class(['badge', 'success' => $session->enabled, 'muted' => !$session->enabled])>{{ $session->enabled ? 'Enabled' : 'Disabled' }}</span></td><td><button type="button" wire:click="edit({{ $session->id }})" class="button secondary small">Configure<span class="sr-only"> {{ $session->label }}</span></button><button type="button" wire:click="lock({{ $session->id }}, {{ $session->attendance_locked_at ? 'false' : 'true' }})" class="button secondary small">{{ $session->attendance_locked_at ? 'Unlock attendance' : 'Lock attendance' }}<span class="sr-only"> {{ $session->label }}</span></button>@if($session->isTest())<button type="button" wire:click="removeTestSession({{ $session->id }})" wire:confirm="Remove this test session? This only works while it has no attendance or guest records." class="button secondary small">Remove test<span class="sr-only"> {{ $session->label }}</span></button>@endif@if($session->attendance_locked_at)<span class="badge">Attendance locked</span>@endif</td></tr>
@endforeach
</tbody></table></div></div>
<section class="panel" style="margin-top:24px"><div class="section-heading"><div><h2>Add a test session</h2><p class="muted-text">Create a clearly marked session for today or another 2026 date to test registration and check-in outside the conference week. It is enabled immediately, so pick a window that includes now.</p></div></div>
<form wire:submit="addTestSession" class="form-stack">
<div class="form-grid"><div><label for="test-date">Test date</label><input id="test-date" type="date" wire:model="testDate"></div><div><label for="test-start">Start time (WAT)</label><input id="test-start" type="time" wire:model="testStart"></div><div><label for="test-end">End time (WAT)</label><input id="test-end" type="time" wire:model="testEnd"></div></div>
<div class="button-row"><button class="button primary" wire:loading.attr="disabled">Add test session</button><span class="muted-text" wire:loading role="status">Saving…</span></div>
</form></section>
@if($editing)
<section class="panel edit-panel" aria-labelledby="edit-heading"><h2 id="edit-heading">Configure {{ $sessions->firstWhere('id', $editing)?->label }}</h2><p class="muted-text">Same-day local times · Africa/Lagos</p><form wire:submit="save" class="form-stack">
<div class="form-grid"><div><label for="start">Start time (WAT)</label><input id="start" type="time" wire:model="start"></div><div><label for="end">End time (WAT)</label><input id="end" type="time" wire:model="end"></div></div>
<label class="checkbox-row"><input type="checkbox" wire:model="enabled"> Enable this session for its configured window</label>
<div class="button-row"><button class="button primary" wire:loading.attr="disabled">Save schedule</button><button type="button" class="button secondary" wire:click="cancel">Cancel</button><span class="muted-text" wire:loading role="status">Saving…</span></div>
</form></section>
@endif
</div>

