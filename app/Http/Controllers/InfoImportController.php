<?php

namespace App\Http\Controllers;

use App\Models\InfoImportBatch;
use App\Models\InfoImportRow;
use App\Services\Audit;
use App\Services\CsvExport;
use App\Services\InfoCsvImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InfoImportController extends Controller
{
    public function index(): View
    {
        return view('info.imports', ['batches' => InfoImportBatch::orderByDesc('id')->paginate(20)]);
    }

    public function store(Request $request, InfoCsvImport $importer): RedirectResponse
    {
        $request->validate(['file' => 'required|file|max:5120']);
        $batch = $importer->preview($request->user(), $request->file('file'));

        return redirect()->route('info.imports.show', $batch)->with('status', 'Preview ready. Nothing is imported until you confirm.');
    }

    public function show(InfoImportBatch $batch): View
    {
        $rows = $batch->rows()->orderBy('row_number')->paginate(25);
        $candidateMap = [];
        foreach ($rows as $row) {
            if ($row->processing_state === 'pending') {
                $candidateMap[$row->id] = app(InfoCsvImport::class)->candidates($row->normalized_json)->limit(10)->get();
            }
        }

        return view('info.import-detail', compact('batch', 'rows', 'candidateMap'));
    }

    public function resolve(Request $request, InfoImportBatch $batch, InfoImportRow $row, InfoCsvImport $importer): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:new,match,skip', 'attendee_id' => 'nullable|required_if:decision,match|integer|exists:info_attendees,id,deleted_at,NULL']);
        $importer->resolve($request->user(), $batch, $row, $data['decision'], isset($data['attendee_id']) ? (int) $data['attendee_id'] : null);

        return back()->with('status', 'Row decision saved.');
    }

    public function commit(Request $request, InfoImportBatch $batch, InfoCsvImport $importer): RedirectResponse
    {
        $request->validate(['confirm' => 'required|accepted']);
        $importer->commit($request->user(), $batch);

        return redirect()->route('info.imports.show', $batch)->with('status', 'Import submitted. Refresh to see progress.');
    }

    public function results(Request $request, InfoImportBatch $batch): StreamedResponse
    {
        app(Audit::class)->record('info_com', 'import.results_exported', $batch, $request->user());
        $rows = (function () use ($batch) {
            foreach ($batch->rows()->orderBy('row_number')->cursor() as $row) {
                yield [$row->row_number, $row->classification, $row->processing_state, $row->info_attendee_id, implode(' | ', $row->error_json ?? [])];
            }
        })();

        return CsvExport::download('info-import-'.$batch->id.'-results.csv', ['Source row', 'Classification', 'Result', 'Attendee ID', 'Validation errors'], $rows);
    }

    public function template(): StreamedResponse
    {
        return CsvExport::download('info-com-registration-template.csv', InfoCsvImport::HEADERS, []);
    }
}
