<?php

namespace App\Http\Controllers;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestImportBatch;
use App\Models\GuestImportRow;
use App\Services\Audit;
use App\Services\CsvExport;
use App\Services\GuestCapture;
use App\Services\GuestImport;
use App\Services\GuestWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestImportController extends Controller
{
    public function index(Request $request, GuestImport $importer): View
    {
        $importer->authorize($request->user());
        $query = GuestImportBatch::query();
        if (! $request->user()->hasRole('super_admin')) {
            $query->where('uploaded_by_user_id', $request->user()->id);
        }

        return view('guest.imports', ['batches' => $query->orderByDesc('id')->paginate(20),
            'events' => ConferenceEvent::orderByDesc('year')->get()]);
    }

    public function store(Request $request, GuestImport $importer): RedirectResponse
    {
        $data = $request->validate(['file' => 'required|file|max:5120', 'event_id' => 'required|integer|exists:conference_events,id']);
        $batch = $importer->upload($request->user(), $request->file('file'), (int) $data['event_id']);

        return redirect()->route('guest-imports.show', $batch)->with('status', 'Upload inspected. Select worksheets and map actual encounter sessions to generate a dry-run preview.');
    }

    public function show(Request $request, GuestImportBatch $batch, GuestImport $importer): View
    {
        $importer->authorize($request->user(), $batch);
        $rows = $batch->rows()->orderBy('worksheet_name')->orderBy('row_number')->paginate(25);
        $candidateMap = [];
        foreach ($rows as $row) {
            if ($row->processing_state === 'pending') {
                $candidateMap[$row->id] = app(GuestCapture::class)->candidates($request->user(), $row->normalized_json)
                    ->where('conference_session_id', $row->normalized_json['conference_session_id'])->limit(10)->get();
            }
        }

        return view('guest.import-detail', ['batch' => $batch, 'rows' => $rows, 'candidateMap' => $candidateMap,
            'event' => ConferenceEvent::findOrFail($batch->conference_event_id),
            'sessions' => ConferenceSession::where('conference_event_id', $batch->conference_event_id)->orderBy('date_local')->orderBy('id')->get()]);
    }

    public function preview(Request $request, GuestImportBatch $batch, GuestImport $importer): RedirectResponse
    {
        $data = $request->validate(['sheets' => 'required|array', 'session_map' => 'required|array', 'source_timezone' => 'required|in:Africa/Lagos,UTC']);
        $importer->preview($request->user(), $batch, $data['sheets'], $data['session_map'], $data['source_timezone']);

        return redirect()->route('guest-imports.show', $batch)->with('status', 'Preview ready. Review flagged rows before confirming. No guests have been created.');
    }

    public function resolve(Request $request, GuestImportBatch $batch, GuestImportRow $row, GuestImport $importer): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:new,match,skip', 'guest_id' => 'nullable|required_if:decision,match|integer']);
        $importer->resolve($request->user(), $batch, $row, $data['decision'], isset($data['guest_id']) ? (int) $data['guest_id'] : null);

        return back()->with('status', 'Review decision saved.');
    }

    public function commit(Request $request, GuestImportBatch $batch, GuestImport $importer): RedirectResponse
    {
        $request->validate(['confirm' => 'required|accepted']);
        $importer->commit($request->user(), $batch);

        return redirect()->route('guest-imports.show', $batch)->with('status', 'Import submitted. Refresh this page to check progress.');
    }

    public function results(Request $request, GuestImportBatch $batch, GuestImport $importer): StreamedResponse
    {
        $importer->authorize($request->user(), $batch);
        app(Audit::class)->record('follow_up', 'import.results_exported', $batch, $request->user());
        $rows = (function () use ($batch) {
            foreach ($batch->rows()->orderBy('worksheet_name')->orderBy('row_number')->cursor() as $row) {
                yield [$row->worksheet_name, $row->row_number, $row->classification, $row->processing_state, $row->guest_record_id,
                    implode(' | ', $row->error_json ?? []), implode(' | ', $row->normalized_json['_review_notes'] ?? [])];
            }
        })();

        return CsvExport::download('guest-import-'.$batch->id.'-results.csv', ['Worksheet', 'Source row', 'Classification', 'Result', 'Guest ID', 'Errors', 'Review notes'], $rows);
    }

    public function template(): StreamedResponse
    {
        return CsvExport::download('guest-service-template.csv', GuestWorkbook::CSV_HEADERS, []);
    }

    public function historicalSession(Request $request): RedirectResponse
    {
        Gate::authorize('platform.manage');
        $data = $request->validate(['year' => 'required|integer|min:2000|max:2025', 'date' => 'required|date_format:Y-m-d',
            'day_number' => 'required|integer|min:1|max:31', 'name' => 'required|in:General,Morning,Evening']);
        if ((int) substr($data['date'], 0, 4) !== (int) $data['year']) {
            throw ValidationException::withMessages(['date' => 'Session date must belong to the selected historical year.']);
        }
        DB::transaction(function () use ($request, $data): void {
            $event = ConferenceEvent::firstOrCreate(['year' => $data['year']], ['name' => 'SUMMIT '.$data['year'],
                'starts_on' => $data['date'], 'ends_on' => $data['date'], 'timezone' => 'Africa/Lagos']);
            $event = ConferenceEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $session = ConferenceSession::firstOrCreate(['code' => $data['date'].'-'.strtoupper($data['name'])],
                ['conference_event_id' => $event->id, 'date_local' => $data['date'], 'day_number' => $data['day_number'],
                    'name' => $data['name'], 'label' => 'Day '.$data['day_number'].' — '.$data['name'], 'enabled' => false]);
            $event->update(['starts_on' => min($event->starts_on->format('Y-m-d'), $data['date']), 'ends_on' => max($event->ends_on->format('Y-m-d'), $data['date'])]);
            app(Audit::class)->record('follow_up', 'historical_session.configured', $session, $request->user());
        });

        return back()->with('status', 'Historical session configured. No clock times or attendance were created.');
    }
}
