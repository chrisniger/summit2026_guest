<x-layout title="Guest import review">
    <div class="page-heading"><div><p class="eyebrow">{{ $event->name }} · GUEST IMPORT #{{ $batch->id }}</p><h1>{{ $batch->filename }}</h1><p>Status: {{ $batch->status }}</p></div><div class="button-row"><a class="button secondary" href="{{ route('guest-imports.index') }}">Import history</a><a class="button secondary" href="{{ route('guest-imports.show', $batch) }}">Refresh progress</a></div></div>
    @if($batch->failure_message)<div class="notice error" role="alert">{{ $batch->failure_message }}</div>@endif
    @if($batch->status === 'uploaded')
        <form method="POST" action="{{ route('guest-imports.preview', $batch) }}" class="panel edit-panel form-stack">
            @csrf<h2>Select worksheets and map encounter sessions</h2>
            <p class="muted-text">Map each worksheet/event descriptor to its actual encounter session. Source timestamps are kept separately. Empty worksheets are ignored.</p>
            <fieldset><legend>Worksheets</legend>@foreach($batch->summary_json['sheets'] as $sheet)<label class="checkbox-row"><input type="checkbox" name="sheets[]" value="{{ $sheet['name'] }}" @checked($sheet['rows'] > 0) @disabled($sheet['rows'] === 0)>{{ $sheet['name'] }} · {{ $sheet['rows'] }} rows</label><p class="muted-text guest-source">{{ implode(' · ', $sheet['headers']) }}</p>@endforeach</fieldset>
            <div><label for="source_timezone">Timezone for source timestamps without an offset *</label><select id="source_timezone" name="source_timezone" required><option value="">Confirm source timezone</option><option value="Africa/Lagos" @selected(old('source_timezone') === 'Africa/Lagos')>Africa/Lagos (WAT)</option><option value="UTC" @selected(old('source_timezone') === 'UTC')>UTC</option></select><small>Used for Excel serial dates and local timestamp text. Explicit ISO offsets are retained.</small></div>
            @foreach($batch->summary_json['groups'] as $key => $group)
                <div><label for="mapping_{{ $key }}">{{ $group['sheet'] }} · {{ $group['descriptor'] ?: 'No source event label' }} · {{ $group['rows'] }} rows</label><select id="mapping_{{ $key }}" name="session_map[{{ $key }}]"><option value="">Select an actual session</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected(old('session_map.'.$key, $group['suggested_session'] ?? null) == $session->id)>{{ $session->date_local->format('d M Y') }} · {{ $session->label }}</option>@endforeach</select></div>
            @endforeach
            <p class="muted-text">Legacy officer and status text will be preserved for review. Imported guests start NOT CONTACTED and UNASSIGNED.</p>
            <button class="button primary">Generate dry-run preview</button>
        </form>
    @else
        <section class="panel edit-panel"><h2>Import progress</h2><ul class="breakdown-list">@foreach(['rows_total' => 'Selected rows', 'rows_processed' => 'Processed / classified', 'rows_review' => 'Awaiting review', 'rows_created' => 'Created', 'rows_matched' => 'Linked to existing encounter', 'rows_skipped' => 'Skipped / duplicate', 'rows_invalid' => 'Invalid — correct in a new upload'] as $key => $label)<li><span>{{ $label }}</span><strong>{{ $batch->$key }}</strong></li>@endforeach</ul>
            <div class="button-row guest-section"><a class="button secondary" href="{{ route('guest-imports.results', $batch) }}">Download row results</a></div>
            @if(in_array($batch->status, ['previewed', 'failed'], true))
                <form method="POST" action="{{ route('guest-imports.commit', $batch) }}" class="form-stack guest-section">@csrf<label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required>I reviewed the rows and confirm importing approved encounters. Invalid and skipped rows stay excluded.</label><button class="button primary" @disabled($batch->rows_review > 0)>{{ $batch->status === 'failed' ? 'Retry remaining rows' : 'Confirm import' }}</button></form>
            @endif
        </section>
        <div class="import-row-list">
            @foreach($rows as $row)
                <article class="panel edit-panel"><div class="card-title"><h2>{{ $row->normalized_json['full_name'] ?? 'Invalid guest' }}</h2><span class="badge">{{ $row->classification }} · {{ $row->processing_state }}</span></div>
                    <p class="muted-text">{{ $row->worksheet_name }} · source row {{ $row->row_number }} · {{ $row->normalized_json['phone_raw'] ?? '' }}</p>
                    @foreach($row->error_json ?? [] as $error)<p class="notice error">{{ $error }}</p>@endforeach
                    @foreach($row->normalized_json['_review_notes'] ?? [] as $note)<p class="muted-text">{{ $note }}</p>@endforeach
                    <details class="additional-details"><summary>Source row</summary><dl class="guest-source">@foreach($row->raw_json as $key => $value)<div><dt>{{ $key }}</dt><dd>{{ is_scalar($value) ? $value : json_encode($value) }}</dd></div>@endforeach</dl></details>
                    @if($row->processing_state === 'pending' && in_array($batch->status, ['previewed', 'failed'], true))
                        <form method="POST" action="{{ route('guest-imports.resolve', [$batch, $row]) }}" class="form-stack">@csrf<div class="form-grid">
                            <div><label for="decision_{{ $row->id }}">Reviewed decision</label><select id="decision_{{ $row->id }}" name="decision" required><option value="">Choose a decision</option><option value="new" @selected($row->classification === 'new')>Create a separate encounter; preserve legacy text</option><option value="skip" @selected($row->classification === 'skip')>Skip this source row</option>@if(($candidateMap[$row->id] ?? collect())->isNotEmpty())<option value="match" @selected($row->classification === 'match')>Use existing encounter; keep its profile and status</option>@endif</select></div>
                            <div><label for="guest_{{ $row->id }}">Existing encounter, if selected</label><select id="guest_{{ $row->id }}" name="guest_id"><option value="">Choose an existing encounter</option>@foreach($candidateMap[$row->id] ?? [] as $candidate)<option value="{{ $candidate->id }}" @selected($row->guest_record_id === $candidate->id)>#{{ $candidate->id }} · {{ $candidate->full_name }} · {{ $candidate->phone_raw }}</option>@endforeach</select></div>
                        </div><button class="button secondary">Save review decision</button></form>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="pagination">{{ $rows->links() }}</div>
    @endif
</x-layout>
