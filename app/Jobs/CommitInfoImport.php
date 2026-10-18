<?php

namespace App\Jobs;

use App\Models\InfoImportBatch;
use App\Models\User;
use App\Services\Audit;
use App\Services\InfoAttendance;
use App\Services\InfoCsvImport;
use App\Services\InfoRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CommitInfoImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $batchId, public int $actorId) {}

    public function handle(): void
    {
        try {
            DB::transaction(function () {
                app(InfoAttendance::class)->lockConference();
                $batch = InfoImportBatch::whereKey($this->batchId)->lockForUpdate()->firstOrFail();
                if ($batch->status !== 'processing') {
                    return;
                }
                $actor = User::findOrFail($this->actorId);
                Gate::forUser($actor)->authorize('info.import');
                $rows = $batch->rows()->where('processing_state', 'pending')->orderBy('id')->limit(100)->lockForUpdate()->get();
                foreach ($rows as $row) {
                    if ($row->classification === 'skip') {
                        $row->update(['processing_state' => 'skipped']);

                        continue;
                    }
                    abort_unless(in_array($row->classification, ['new', 'match'], true), 409, 'Row requires review.');
                    $data = $row->normalized_json;
                    $instant = CarbonImmutable::parse($data['_original_at']);
                    unset($data['_original_at']);
                    $data['raw_source_json'] = $row->raw_json;
                    $result = app(InfoRegistration::class)->register($actor, $data, $instant, $row->row_fingerprint, 'csv', $row->id, $row->classification === 'match' ? $row->info_attendee_id : null);
                    $row->update(['info_attendee_id' => $result['attendee']->id,
                        'processing_state' => $result['replayed'] ? 'duplicate' : ($result['created'] ? 'created' : 'matched')]);
                }
                app(InfoCsvImport::class)->summarize($batch);
                if ($batch->rows()->where('processing_state', 'pending')->exists()) {
                    self::dispatch($batch->id, $actor->id)->onQueue('imports')->beforeCommit();
                } else {
                    $batch->update(['status' => 'completed']);
                    app(Audit::class)->record('info_com', 'import.completed', $batch, $actor, [], $batch->only(['rows_total', 'rows_created', 'rows_matched', 'rows_skipped']));
                }
            }, 3);
        } catch (\Throwable $error) {
            Log::error('Info Com import chunk paused.', ['batch_id' => $this->batchId, 'exception_class' => $error::class]);
            InfoImportBatch::whereKey($this->batchId)->where('status', 'processing')->update(['status' => 'failed', 'failure_message' => 'Import paused. The interrupted chunk was rolled back. Review permissions and retry; completed rows will not be imported twice.']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        InfoImportBatch::whereKey($this->batchId)->where('status', 'processing')->update(['status' => 'failed', 'failure_message' => 'The worker stopped before completing this import. Retry safely from the remaining rows.']);
    }
}
