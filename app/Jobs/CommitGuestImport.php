<?php

namespace App\Jobs;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestImportBatch;
use App\Models\GuestRecord;
use App\Models\User;
use App\Services\Audit;
use App\Services\GuestCapture;
use App\Services\GuestImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class CommitGuestImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $batchId, public int $actorId) {}

    public function handle(): void
    {
        try {
            DB::transaction(function (): void {
                $eventId = GuestImportBatch::whereKey($this->batchId)->value('conference_event_id');
                ConferenceEvent::whereKey($eventId)->lockForUpdate()->firstOrFail();
                $batch = GuestImportBatch::whereKey($this->batchId)->lockForUpdate()->firstOrFail();
                if ($batch->status !== 'processing') {
                    return;
                }
                $actor = User::findOrFail($this->actorId);
                $importer = app(GuestImport::class);
                $importer->authorize($actor, $batch);
                $rows = $batch->rows()->where('processing_state', 'pending')->orderBy('id')->limit(100)->lockForUpdate()->get();
                foreach ($rows as $row) {
                    if ($row->classification === 'skip') {
                        $row->update(['processing_state' => 'skipped']);

                        continue;
                    }
                    abort_unless(in_array($row->classification, ['new', 'match'], true), 409, 'Row needs review.');
                    $completed = $importer->processed($row->row_fingerprint)->lockForUpdate()->first();
                    if ($completed) {
                        $existing = GuestRecord::visibleTo($actor)->find($completed->guest_record_id);
                        $row->update(['processing_state' => 'duplicate', 'guest_record_id' => $existing?->id]);

                        continue;
                    }
                    $data = $row->normalized_json;
                    ConferenceSession::where('conference_event_id', $eventId)->findOrFail($data['conference_session_id']);
                    if ($row->classification === 'match') {
                        $guest = GuestRecord::visibleTo($actor)->whereNull('archived_at')->lockForUpdate()->findOrFail($row->guest_record_id);
                        Gate::forUser($actor)->authorize('update', $guest);
                        abort_unless($guest->conference_session_id === (int) $data['conference_session_id'], 409);
                        $state = 'matched';
                    } else {
                        $guest = app(GuestCapture::class)->insert($actor, $data, $row->row_fingerprint, $row->id, $row->raw_json);
                        $state = 'created';
                    }
                    $row->update(['guest_record_id' => $guest->id, 'processing_state' => $state]);
                }
                $importer->summarize($batch);
                if ($batch->rows()->where('processing_state', 'pending')->exists()) {
                    self::dispatch($batch->id, $actor->id)->onQueue('imports')->beforeCommit();
                } else {
                    $batch->update(['status' => 'completed']);
                    app(Audit::class)->record('follow_up', 'import.completed', $batch, $actor, [], $batch->only(['rows_total', 'rows_created', 'rows_matched', 'rows_skipped']));
                }
            }, 3);
        } catch (\Throwable $error) {
            Log::error('Guest import chunk paused.', ['batch_id' => $this->batchId, 'exception_class' => $error::class]);
            GuestImportBatch::whereKey($this->batchId)->where('status', 'processing')->update(['status' => 'failed',
                'failure_message' => 'Import paused. The interrupted chunk was rolled back. Review data and permissions, then retry; completed rows remain imported once.']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        GuestImportBatch::whereKey($this->batchId)->where('status', 'processing')->update(['status' => 'failed', 'failure_message' => 'Worker interrupted. Retry safely from remaining rows.']);
    }
}
