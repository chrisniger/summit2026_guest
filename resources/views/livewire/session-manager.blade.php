<div>
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
<div class="panel"><div class="section-heading"><div><h2>Session schedule</h2><p class="muted-text">No times are assumed. Enabled windows cannot overlap. End times are exclusive.</p></div><span class="badge outline">UTC +01:00</span></div>
<div class="table-wrap"><table><thead><tr><th>Conference session</th><th>Local date</th><th>WAT window</th><th>UTC window</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
@foreach($sessions as $session)
<tr wire:key="session-{{ $session->id }}"><td><strong>{{ $session->label }}</strong><small>{{ $session->name }}</small></td><td>{{ $session->date_local->format('D, j M Y') }}</td><td>{{ $session->starts_at_utc ? $session->starts_at_utc->setTimezone(config('summit.timezone'))->format('H:i').' – '.$session->ends_at_utc->setTimezone(config('summit.timezone'))->format('H:i') : 'Not configured' }}</td><td>{{ $session->starts_at_utc ? $session->starts_at_utc->format('H:i').' – '.$session->ends_at_utc->format('H:i') : '—' }}</td><td><span @class(['badge', 'success' => $session->enabled, 'muted' => !$session->enabled])>{{ $session->enabled ? 'Enabled' : 'Disabled' }}</span></td><td><button type="button" wire:click="edit({{ $session->id }})" class="button secondary small">Configure<span class="sr-only"> {{ $session->label }}</span></button></td></tr>
@endforeach
</tbody></table></div></div>
@if($editing)
<section class="panel edit-panel" aria-labelledby="edit-heading"><h2 id="edit-heading">Configure {{ $sessions->firstWhere('id', $editing)?->label }}</h2><p class="muted-text">Same-day local times · Africa/Lagos</p><form wire:submit="save" class="form-stack">
@if($errors->any())<div class="notice error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="form-grid"><div><label for="start">Start time (WAT)</label><input id="start" type="time" wire:model="start"></div><div><label for="end">End time (WAT)</label><input id="end" type="time" wire:model="end"></div></div>
<label class="checkbox-row"><input type="checkbox" wire:model="enabled"> Enable this session for its configured window</label>
<div class="button-row"><button class="button primary" wire:loading.attr="disabled">Save schedule</button><button type="button" class="button secondary" wire:click="cancel">Cancel</button><span class="muted-text" wire:loading role="status">Saving…</span></div>
</form></section>
@endif
</div>

