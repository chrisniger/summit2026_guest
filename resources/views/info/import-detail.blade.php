<x-layout title="Import review">
<div class="page-heading"><div><span class="eyebrow">INFO COM · IMPORT #{{ $batch->id }}</span><h1>Review your import</h1><p>{{ $batch->filename }} · {{ ucfirst($batch->status) }}</p></div><div class="button-row"><a class="button secondary" href="{{ route('info.imports') }}">All imports</a><a class="button secondary" href="{{ route('info.imports.results',$batch) }}">Download row results</a><a class="button secondary" href="{{ request()->fullUrl() }}">Refresh progress</a></div></div>
<div class="panel edit-panel" style="margin-top:0"><div class="metric-row"><div><strong>{{ $batch->rows_total }}</strong><span>Source rows</span></div><div><strong>{{ $batch->rows_processed }}</strong><span>Processed</span></div><div><strong>{{ $batch->rows_created }}</strong><span>New attendees</span></div><div><strong>{{ $batch->auto_attendance_created }}</strong><span>Auto check-ins</span></div></div><p class="muted-text" style="margin-top:16px">{{ $batch->rows_matched }} matched registrations · {{ $batch->rows_skipped }} skipped/duplicate · {{ $batch->rows_invalid }} invalid · {{ $batch->rows_review }} awaiting review</p>
@if($batch->failure_message)<div class="notice error" role="alert" style="margin-top:16px">{{ $batch->failure_message }}</div>@endif
@if(in_array($batch->status,['previewed','failed']))
<form method="POST" action="{{ route('info.imports.commit',$batch) }}" class="form-stack" style="margin-top:20px">@csrf<label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required> I reviewed this preview. Import accepted rows and skip invalid, duplicate or explicitly skipped rows.</label><div><button class="button primary" @disabled($batch->rows_review>0)>{{ $batch->status==='failed'?'Retry remaining rows':'Confirm import' }}</button></div>@if($batch->rows_review)<p class="muted-text">Resolve {{ $batch->rows_review }} possible duplicate rows first, including rows on other pages.</p>@endif</form>
@elseif($batch->status==='processing')<div class="notice success" role="status" style="margin-top:16px">Queued / processing in chunks. Refresh this page to see updated counts.</div>@endif
</div>
<div class="import-row-list">
@foreach($rows as $row)
<article class="panel edit-panel"><div class="section-heading" style="padding:0 0 16px"><div><span class="eyebrow">SOURCE ROW {{ $row->row_number }} · {{ strtoupper($row->processing_state) }}</span><h2>{{ $row->raw_json['First Name']??'Unmapped row' }} {{ $row->raw_json['Surname']??'' }}</h2><p class="muted-text">{{ $row->raw_json['Phone']??'' }} · Original time: {{ $row->raw_json['Created']??'Missing' }}</p></div><span class="badge {{ $row->classification==='review'?'warning':'muted' }}">{{ ucfirst($row->classification) }}</span></div>
@if($row->error_json)<div class="notice error"><ul>@foreach($row->error_json as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($row->classification==='review')<p class="muted-text">This row may share a phone/name with another registration. It has not been merged. Compare records before deciding.</p>@endif
@if($row->processing_state==='pending' && in_array($batch->status,['previewed','failed']))
<details class="additional-details" @if($row->classification==='review') open @endif><summary>{{ $row->classification==='review'?'Review this possible duplicate':'Change row decision (optional)' }}</summary>
@php($candidates=$candidateMap[$row->id]??collect())
@if($candidates->isNotEmpty())<ul class="candidate-list">@foreach($candidates as $candidate)<li>#{{ $candidate->id }} · <a class="text-link" href="{{ route('info.attendees.show',$candidate) }}" target="_blank" rel="noopener">{{ $candidate->first_name }} {{ $candidate->last_name }}</a> · {{ $candidate->phone_raw }}</li>@endforeach</ul>@endif
<form class="form-stack" method="POST" action="{{ route('info.imports.resolve',[$batch,$row]) }}" style="margin-top:16px">@csrf<div class="form-grid"><div><label for="decision-{{ $row->id }}">Row decision</label><select id="decision-{{ $row->id }}" name="decision" required><option value="new" @selected($row->classification==='new')>Create a separate attendee</option><option value="match" @selected($row->classification==='match')>Add registration to a reviewed attendee ID</option><option value="skip" @selected($row->classification==='skip')>Skip this source row</option></select></div><div><label for="match-{{ $row->id }}">Reviewed attendee ID (match only)</label><input id="match-{{ $row->id }}" name="attendee_id" type="number" min="1" value="{{ $row->info_attendee_id }}" placeholder="Existing Info Com ID"></div></div><div><button class="button secondary">Save decision for row {{ $row->row_number }}</button></div></form>
</details>
@endif
</article>
@endforeach
</div><div class="pagination">{{ $rows->links() }}</div>
</x-layout>

