<x-layout title="Guest imports">
    <div class="page-heading"><div><p class="eyebrow">GUEST SERVICE</p><h1>Guest imports</h1><p>Preview guest CSV files and historical 2025 XLSX workbooks.</p></div><a class="button secondary" href="{{ route('guest-imports.template') }}">Download CSV template</a></div>
    <form class="panel edit-panel form-stack" method="POST" enctype="multipart/form-data" action="{{ route('guest-imports.store') }}">
        @csrf<h2>Upload for inspection</h2><div class="form-grid"><div><label for="event_id">Target event year *</label><select id="event_id" name="event_id" required>@foreach($events as $event)<option value="{{ $event->id }}" @selected(old('event_id') == $event->id)>{{ $event->name }}</option>@endforeach</select></div>
            <div><label for="file">Guest CSV or historical XLSX *</label><input id="file" name="file" type="file" accept=".csv,.xlsx" required><small>Maximum 5 MB, 10,000 non-empty rows. XLSX must target configured 2025 sessions.</small></div></div>
        <button class="button primary">Inspect upload</button>
    </form>
    <div class="panel guest-section"><div class="section-heading"><h2>Import history</h2></div><div class="table-wrap"><table><thead><tr><th>File</th><th>Status</th><th>Rows</th><th>Created</th><th>Skipped / invalid</th></tr></thead><tbody>@forelse($batches as $batch)<tr><td><a class="text-link" href="{{ route('guest-imports.show', $batch) }}">{{ $batch->filename }}</a><small>Batch #{{ $batch->id }}</small></td><td>{{ $batch->status }}</td><td>{{ $batch->rows_total }}</td><td>{{ $batch->rows_created }}</td><td>{{ $batch->rows_skipped }} / {{ $batch->rows_invalid }}</td></tr>@empty<tr><td colspan="5">No guest imports yet.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $batches->links() }}</div></div>
    @can('platform.manage')
        <details class="panel edit-panel additional-details"><summary>Configure a historical encounter session</summary><p class="muted-text">Enter verified historical dates and classifications before mapping a workbook. Clock times remain unset.</p>
            <form class="form-stack" method="POST" action="{{ route('guest-imports.historical-session') }}">@csrf<div class="form-grid">
                <div><label for="historical_year">Historical year *</label><input id="historical_year" type="number" name="year" min="2000" max="2025" value="2025" required></div>
                <div><label for="historical_date">Actual session date *</label><input id="historical_date" type="date" name="date" value="{{ old('date') }}" required></div>
                <div><label for="historical_day">Conference day number *</label><input id="historical_day" type="number" name="day_number" min="1" max="31" value="{{ old('day_number') }}" required></div>
                <div><label for="historical_name">Session classification *</label><select id="historical_name" name="name" required><option value="">Choose classification</option>@foreach(['General', 'Morning', 'Evening'] as $name)<option>{{ $name }}</option>@endforeach</select></div>
            </div><button class="button secondary">Add historical session</button></form>
        </details>
    @endcan
</x-layout>
