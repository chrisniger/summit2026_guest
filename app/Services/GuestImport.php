<?php

namespace App\Services;

use App\Http\Requests\GuestRequest;
use App\Jobs\CommitGuestImport;
use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestImportBatch;
use App\Models\GuestImportRow;
use App\Models\GuestRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestImport
{
    public function authorize(User $actor, ?GuestImportBatch $batch = null): void
    {
        Gate::forUser($actor)->authorize('guest.import');
        Gate::forUser($actor)->authorize('create', GuestRecord::class);
        if ($batch && ! $actor->hasRole('super_admin')) {
            abort_unless($batch->uploaded_by_user_id === $actor->id, 403);
        }
    }

    public function upload(User $actor, UploadedFile $file, int $eventId): GuestImportBatch
    {
        $this->authorize($actor);
        Validator::make(['file' => $file], ['file' => 'required|file|extensions:csv,xlsx|max:5120'])->validate();
        $event = ConferenceEvent::findOrFail($eventId);
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === 'xlsx' && $event->year !== 2025) {
            throw ValidationException::withMessages(['event_id' => 'Historical XLSX imports must target explicitly configured 2025 sessions. Use the guest CSV template for current captures.']);
        }
        $mime = $file->getMimeType();
        $allowed = $extension === 'csv' ? ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel']
            : ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'];
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'File content does not match a supported CSV/XLSX upload.']);
        }
        $content = file_get_contents($file->getRealPath());
        $parsed = app(GuestWorkbook::class)->read($content, $extension);
        if (! $parsed['rows']) {
            throw ValidationException::withMessages(['file' => 'The file contains no guest rows.']);
        }
        $groups = [];
        foreach ($parsed['rows'] as $row) {
            $key = $this->groupKey($row);
            $groups[$key] ??= ['sheet' => $row['sheet'], 'descriptor' => trim(($row['mapped']['event_date'] ?? '').' '.($row['mapped']['event'] ?? '')), 'rows' => 0];
            $groups[$key]['rows']++;
            if ($extension === 'csv') {
                $date = $row['mapped']['event_date'] ?? '';
                $name = $this->sessionName($row['mapped']['event'] ?? '');
                $sessions = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                    ? ConferenceSession::where('conference_event_id', $eventId)->where('date_local', $date)->where('name', $name)->get()
                    : collect();
                if ($sessions->count() === 1) {
                    $groups[$key]['suggested_session'] = $sessions->first()->id;
                }
            }
        }
        if (count($groups) > 200) {
            throw ValidationException::withMessages(['file' => 'Use at most 200 distinct worksheet/event descriptors per upload. Split this file or correct inconsistent event labels.']);
        }
        $path = 'guest-imports/'.Str::uuid().'.'.$extension.'.enc';
        if (! Storage::disk('local')->put($path, Crypt::encryptString($content))) {
            throw ValidationException::withMessages(['file' => 'The private original could not be saved. Check storage and retry. No import was created.']);
        }
        try {
            return DB::transaction(function () use ($actor, $eventId, $file, $content, $path, $extension, $parsed, $groups): GuestImportBatch {
                $batch = GuestImportBatch::create(['conference_event_id' => $eventId, 'filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'sha256' => hash('sha256', $content), 'status' => 'uploaded', 'uploaded_by_user_id' => $actor->id,
                    'private_path' => $path, 'rows_total' => count($parsed['rows']),
                    'summary_json' => ['extension' => $extension, 'sheets' => $parsed['sheets'], 'groups' => $groups]]);
                app(Audit::class)->record('follow_up', 'import.uploaded', $batch, $actor, [], ['rows_total' => count($parsed['rows'])]);

                return $batch;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    private function groupKey(array $row): string
    {
        return hash('sha256', json_encode([$row['sheet'], $row['mapped']['event_date'] ?? '', $row['mapped']['event'] ?? ''], JSON_THROW_ON_ERROR));
    }

    private function sessionName(string $value): string
    {
        return match (mb_strtolower(trim($value))) {
            'general' => 'General', 'morning', 'am' => 'Morning', 'evening', 'pm' => 'Evening', default => ''
        };
    }

    public function preview(User $actor, GuestImportBatch $batch, array $sheets, array $sessionMap, string $timezone): void
    {
        $this->authorize($actor, $batch);
        Validator::make(['timezone' => $timezone, 'sheets' => $sheets, 'session_map' => $sessionMap],
            ['timezone' => 'required|in:Africa/Lagos,UTC', 'sheets' => 'required|array|min:1', 'sheets.*' => 'required|string|max:255',
                'session_map' => 'required|array', 'session_map.*' => 'nullable|integer|exists:conference_sessions,id'])->validate();
        $content = Crypt::decryptString(Storage::disk('local')->get($batch->private_path));
        $parsed = app(GuestWorkbook::class)->read($content, $batch->summary_json['extension']);
        if (array_diff($sheets, array_column($parsed['sheets'], 'name'))) {
            throw ValidationException::withMessages(['sheets' => 'Choose only worksheets in this upload.']);
        }
        $rows = [];
        $seenPhones = [];
        $seenNames = [];
        foreach ($parsed['rows'] as $source) {
            if (! in_array($source['sheet'], $sheets, true)) {
                continue;
            }
            $key = $this->groupKey($source);
            $session = ConferenceSession::where('conference_event_id', $batch->conference_event_id)->find($sessionMap[$key] ?? null);
            if (! $session) {
                throw ValidationException::withMessages(['session_map' => 'Map every selected worksheet/event descriptor to a session in the selected event year.']);
            }
            $raw = $source['raw'];
            $data = $source['mapped'];
            $errors = $source['errors'];
            $warnings = [];
            if ($batch->summary_json['extension'] === 'csv') {
                if (($data['event_date'] ?? '') !== $session->date_local->format('Y-m-d') || $this->sessionName($data['event'] ?? '') !== $session->name) {
                    $errors[] = 'Event Date and Event Session must exactly resolve to the selected authoritative session. Use YYYY-MM-DD and General/Morning/Evening.';
                }
            }
            foreach (['full_name', 'phone_raw', 'first_name', 'last_name', 'gender', 'home_address', 'heard_about', 'entry_notes'] as $field) {
                $data[$field] = ($data[$field] ?? '') === '' ? null : $data[$field];
            }
            $data['conference_session_id'] = $session->id;
            if (isset($data['captured_at']) && $data['captured_at'] !== '') {
                try {
                    $data['captured_at_original_utc'] = $this->timestamp($data['captured_at'], $timezone);
                } catch (\Throwable) {
                    $errors[] = 'Source timestamp is invalid, ambiguous or in the future. Use an ISO timestamp, YYYY-MM-DD HH:MM[:SS], or a standard Excel date serial.';
                }
            }
            if (! empty($data['legacy_officer'])) {
                $warnings[] = 'Legacy officer text is unverified and will be preserved without creating an assignment or account.';
                $data['_legacy_officer'] = $data['legacy_officer'];
            }
            if (! empty($data['legacy_status'])) {
                $warnings[] = 'Legacy status/comment needs review. Import keeps raw history and starts NOT CONTACTED; it does not infer successful contact.';
            }
            foreach ($raw as $value) {
                if (is_string($value) && (preg_match('/^\s*[=@]/u', $value) || preg_match('/#(?:NAME\?|REF!|VALUE!|DIV\/0!|N\/A|NUM!|NULL!)/i', $value))) {
                    $errors[] = 'Formula-like or spreadsheet-error values must be corrected to reviewed literal values.';
                    break;
                }
            }
            $errors = [...$errors, ...Validator::make($data, GuestRequest::fields())->errors()->all()];
            $errors = [...$errors, ...Validator::make($data, ['legacy_officer' => 'nullable|string|max:255', 'legacy_status' => 'nullable|string|max:255'])->errors()->all()];
            $canonical = $raw;
            ksort($canonical);
            $fingerprint = hash('sha256', 'guest-source:'.json_encode([$source['sheet'], $source['number'], $session->id, $canonical], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $phone = PhoneNumber::normalize($data['phone_raw']);
            $name = mb_strtolower($data['full_name'] ?? '');
            $classification = 'new';
            if ($errors) {
                $classification = 'invalid';
            } elseif ($this->processed($fingerprint)->exists()) {
                $classification = 'duplicate';
            } elseif ($warnings || isset($seenPhones[$phone]) || isset($seenNames[$name]) || app(GuestCapture::class)->candidates($actor, $data)->exists()) {
                $classification = 'review';
            }
            if (! $errors) {
                $seenPhones[$phone] = true;
                $seenNames[$name] = true;
            }
            $data['_review_notes'] = $warnings;
            $rows[] = ['worksheet_name' => $source['sheet'], 'row_number' => $source['number'], 'raw_json' => $raw,
                'normalized_json' => $data, 'row_fingerprint' => $fingerprint, 'classification' => $classification,
                'error_json' => $errors ?: null, 'processing_state' => match ($classification) {
                    'invalid' => 'invalid', 'duplicate' => 'duplicate', default => 'pending'
                }];
        }
        if (! $rows) {
            throw ValidationException::withMessages(['sheets' => 'Select at least one non-empty worksheet.']);
        }
        DB::transaction(function () use ($batch, $actor, $rows, $sheets, $sessionMap, $timezone): void {
            $batch = GuestImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($batch->status === 'uploaded', 409, 'This upload has already been previewed. Upload a corrected file to remap.');
            foreach ($rows as $row) {
                $batch->rows()->create($row);
            }
            $batch->update(['status' => 'previewed', 'mapping_json' => ['sheets' => $sheets, 'sessions' => $sessionMap, 'timezone' => $timezone]]);
            $this->summarize($batch);
            app(Audit::class)->record('follow_up', 'import.previewed', $batch, $actor, [], ['rows_total' => count($rows)]);
        });
    }

    private function timestamp(string $value, string $timezone): string
    {
        if (preg_match('/^\d{4,5}(?:\.\d+)?$/D', $value)) {
            $instant = CarbonImmutable::create(1899, 12, 30, 0, 0, 0, $timezone)->addSeconds((int) round((float) $value * 86400));
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            $instant = CarbonImmutable::parse($value);
            if (CarbonImmutable::getLastErrors() && CarbonImmutable::getLastErrors()['warning_count'] > 0) {
                throw new \InvalidArgumentException;
            }
        } else {
            $format = strlen($value) === 16 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
            $instant = CarbonImmutable::createFromFormat($format, $value, $timezone);
            if (! $instant || $instant->format(substr($format, 1)) !== $value) {
                throw new \InvalidArgumentException;
            }
        }
        if ($instant->year < 2000 || $instant->isFuture()) {
            throw new \InvalidArgumentException;
        }

        return $instant->utc()->format('Y-m-d\TH:i:sP');
    }

    public function processed(string $fingerprint): Builder
    {
        return GuestImportRow::where('row_fingerprint', $fingerprint)->whereIn('processing_state', ['created', 'matched']);
    }

    public function resolve(User $actor, GuestImportBatch $batch, GuestImportRow $row, string $decision, ?int $guestId = null): void
    {
        $this->authorize($actor, $batch);
        Validator::make(['decision' => $decision, 'guest_id' => $guestId],
            ['decision' => 'required|in:new,match,skip', 'guest_id' => 'nullable|required_if:decision,match|integer'])->validate();
        DB::transaction(function () use ($actor, $batch, $row, $decision, $guestId): void {
            $batch = GuestImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $row = $batch->rows()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($batch->status, ['previewed', 'failed'], true) && $row->processing_state === 'pending', 409);
            if ($decision === 'match') {
                $guest = GuestRecord::visibleTo($actor)->whereNull('archived_at')->findOrFail($guestId);
                Gate::forUser($actor)->authorize('update', $guest);
                if ($guest->conference_session_id !== (int) $row->normalized_json['conference_session_id']) {
                    throw ValidationException::withMessages(['guest_id' => 'An existing encounter must belong to the same session. Choose a new encounter for a different session.']);
                }
            }
            $row->update(['classification' => $decision, 'guest_record_id' => $decision === 'match' ? $guestId : null]);
            $this->summarize($batch);
            app(Audit::class)->record('follow_up', 'import.row_reviewed', $row, $actor, [], ['classification' => $decision]);
        });
    }

    public function commit(User $actor, GuestImportBatch $batch): void
    {
        $this->authorize($actor, $batch);
        DB::transaction(function () use ($actor, $batch): void {
            $batch = GuestImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (in_array($batch->status, ['processing', 'completed'], true)) {
                return;
            }
            abort_unless(in_array($batch->status, ['previewed', 'failed'], true), 409, 'Preview this upload first.');
            if ($batch->rows()->where('processing_state', 'pending')->where('classification', 'review')->exists()) {
                throw ValidationException::withMessages(['import' => 'Resolve every review row before confirming this import.']);
            }
            $batch->update(['status' => 'processing', 'failure_message' => null]);
            app(Audit::class)->record('follow_up', 'import.committed', $batch, $actor);
            CommitGuestImport::dispatch($batch->id, $actor->id)->onQueue('imports')->beforeCommit();
        });
    }

    public function summarize(GuestImportBatch $batch): void
    {
        $states = $batch->rows()->selectRaw('processing_state, COUNT(*) AS total')->groupBy('processing_state')->pluck('total', 'processing_state');
        $batch->update(['rows_total' => $batch->rows()->count(),
            'rows_valid' => $batch->rows()->whereIn('classification', ['new', 'match'])->count(),
            'rows_review' => $batch->rows()->where('classification', 'review')->where('processing_state', 'pending')->count(),
            'rows_invalid' => (int) ($states['invalid'] ?? 0), 'rows_processed' => $batch->rows()->where('processing_state', '!=', 'pending')->count(),
            'rows_created' => (int) ($states['created'] ?? 0), 'rows_matched' => (int) ($states['matched'] ?? 0),
            'rows_skipped' => (int) ($states['skipped'] ?? 0) + (int) ($states['duplicate'] ?? 0)]);
    }
}
