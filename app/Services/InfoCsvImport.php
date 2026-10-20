<?php

namespace App\Services;

use App\Http\Requests\InfoAttendeeRequest;
use App\Jobs\CommitInfoImport;
use App\Models\ConferenceEvent;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Models\InfoImportBatch;
use App\Models\InfoImportRow;
use App\Models\InfoRegistrationEvent;
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

class InfoCsvImport
{
    public const HEADERS = ['Created', 'First Name', 'Surname', 'Phone', 'Email', 'Member', 'Summit Centre', 'Commits to Invite', 'Invite Count', 'Location', 'Abuja Area', 'First Time', 'Bus Shuttle', 'Bus Route', 'Attendance', 'Heard About', 'Feedback', 'Consent'];

    private const MAP = ['First Name' => 'first_name', 'Surname' => 'last_name', 'Phone' => 'phone_raw', 'Email' => 'email', 'Member' => 'member', 'Summit Centre' => 'summit_centre', 'Commits to Invite' => 'commits_to_invite', 'Invite Count' => 'invite_count', 'Location' => 'location', 'Abuja Area' => 'abuja_area', 'First Time' => 'first_time', 'Bus Shuttle' => 'bus_shuttle', 'Bus Route' => 'bus_route', 'Attendance' => 'attendance_preference', 'Heard About' => 'heard_about', 'Feedback' => 'feedback', 'Consent' => 'consent'];

    public function candidates(array $data): Builder
    {
        return InfoAttendee::where(function ($query) use ($data) {
            $query->where('phone_normalized', PhoneNumber::normalize($data['phone_raw'] ?? ''));
            if (! empty($data['first_name']) && ! empty($data['last_name'])) {
                $query->orWhere(fn ($name) => $name->where('first_name', $data['first_name'])->where('last_name', $data['last_name']));
            }
        });
    }

    public function preview(User $actor, UploadedFile $file): InfoImportBatch
    {
        Gate::forUser($actor)->authorize('info.import');
        Validator::make(['file' => $file], ['file' => 'required|file|extensions:csv|mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel|mimes:csv,txt|max:5120'])->validate();
        $content = file_get_contents($file->getRealPath());
        if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            throw ValidationException::withMessages(['file' => 'Upload a UTF-8 CSV without binary content.']);
        }
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, preg_replace('/^\\xEF\\xBB\\xBF/', '', $content));
        rewind($stream);
        $headers = fgetcsv($stream, null, ',', '"', '');
        $headers = $headers ? array_map('trim', $headers) : [];
        if (count($headers) !== 18 || count(array_unique($headers)) !== 18 || array_diff(self::HEADERS, $headers)) {
            fclose($stream);
            throw ValidationException::withMessages(['file' => 'Use the Info Com template with all 18 named columns. Column order may vary.']);
        }
        $rows = [];
        $seen = [];
        $seenPhones = [];
        $seenNames = [];
        $rowNumber = 1;
        try {
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $rowNumber++;
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($rows) >= 10000) {
                    throw ValidationException::withMessages(['file' => 'Upload at most 10,000 non-empty rows per batch.']);
                }
                $raw = count($values) === 18 ? array_combine($headers, $values) : ['unmapped_values' => $values];
                $canonical = $raw;
                ksort($canonical);
                $fingerprint = hash('sha256', 'info_csv:'.json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $errors = [];
                $data = [];
                $classification = 'new';
                if (count($values) !== 18) {
                    $errors = ['Row has '.count($values).' cells; expected 18.'];
                } else {
                    foreach (self::MAP as $header => $field) {
                        $value = trim((string) ($raw[$header] ?? ''));
                        $data[$field] = $value === '' ? null : $value;
                    }
                    foreach (['member', 'first_time', 'consent', 'commits_to_invite', 'bus_shuttle'] as $field) {
                        if ($data[$field] !== null) {
                            $data[$field] = match (strtolower($data[$field])) {
                                'yes', 'true', '1' => true, 'no', 'false', '0' => false, default => $data[$field]
                            };
                        }
                    }
                    $validator = Validator::make($data, InfoAttendeeRequest::fields());
                    $errors = $validator->errors()->all();
                    try {
                        $stamp = trim((string) $raw['Created']);
                        if (! preg_match('/^(2026)-(\\d{2})-(\\d{2})T([01]\\d|2[0-3]):[0-5]\\d:[0-5]\\d(?:\\.\\d{1,6})?(?:Z|[+-](?:0\\d|1[0-4]):[0-5]\\d)$/D', $stamp, $parts)
                            || ! checkdate((int) $parts[2], (int) $parts[3], 2026)) {
                            throw new \InvalidArgumentException;
                        }
                        $instant = CarbonImmutable::parse($stamp)->utc();
                        if ($instant->isFuture()) {
                            throw new \InvalidArgumentException;
                        }
                        $data['_original_at'] = $instant->toIso8601String();
                    } catch (\Throwable) {
                        $errors[] = 'Created must be a valid 2026 ISO timestamp with timezone and cannot be in the future.';
                    }
                }
                if ($errors) {
                    $classification = 'invalid';
                } elseif (isset($seen[$fingerprint]) || InfoRegistrationEvent::where('source_fingerprint', $fingerprint)->exists()) {
                    $classification = 'duplicate';
                } else {
                    $phone = PhoneNumber::normalize($data['phone_raw']);
                    $name = mb_strtolower($data['first_name'].' '.$data['last_name']);
                    if (isset($seenPhones[$phone]) || isset($seenNames[$name]) || $this->candidates($data)->exists()) {
                        $classification = 'review';
                    }
                    $seenPhones[$phone] = true;
                    $seenNames[$name] = true;
                }
                $seen[$fingerprint] = true;
                $rows[] = ['row_number' => $rowNumber, 'raw_json' => $raw, 'normalized_json' => $data, 'row_fingerprint' => $fingerprint,
                    'classification' => $classification, 'error_json' => $errors ?: null,
                    'processing_state' => match ($classification) {
                        'invalid' => 'invalid', 'duplicate' => 'duplicate', default => 'pending'
                    }];
            }
        } finally {
            fclose($stream);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'The CSV has no data rows.']);
        }
        $path = 'info-imports/'.Str::uuid().'.csv.enc';
        if (! Storage::disk('local')->put($path, Crypt::encryptString($content))) {
            throw ValidationException::withMessages(['file' => 'The private original could not be saved. Check storage and retry. No import was created.']);
        }
        try {
            return DB::transaction(function () use ($actor, $file, $content, $path, $rows) {
                $batch = InfoImportBatch::create(['conference_event_id' => ConferenceEvent::where('year', 2026)->firstOrFail()->id,
                    'filename' => mb_substr($file->getClientOriginalName(), 0, 255), 'sha256' => hash('sha256', $content),
                    'status' => 'previewed', 'uploaded_by_user_id' => $actor->id, 'private_path' => $path, 'mapping_json' => self::MAP]);
                foreach ($rows as $row) {
                    $batch->rows()->create($row);
                }
                $this->summarize($batch);
                app(Audit::class)->record('info_com', 'import.previewed', $batch, $actor, [], ['rows_total' => count($rows)]);

                return $batch->fresh();
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function resolve(User $actor, InfoImportBatch $batch, InfoImportRow $row, string $decision, ?int $attendeeId): void
    {
        Gate::forUser($actor)->authorize('info.import');
        DB::transaction(function () use ($batch, $row, $decision, $attendeeId, $actor) {
            $batch = InfoImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $row = $batch->rows()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($batch->status, ['previewed', 'failed'], true) && $row->processing_state === 'pending' && in_array($row->classification, ['new', 'review', 'match', 'skip'], true), 409, 'This row can no longer be changed.');
            if ($decision === 'match') {
                InfoAttendee::findOrFail($attendeeId);
            }
            $row->update(['classification' => $decision, 'info_attendee_id' => $decision === 'match' ? $attendeeId : null]);
            $this->summarize($batch);
            app(Audit::class)->record('info_com', 'import.row_reviewed', $row, $actor, [], ['classification' => $decision]);
        });
    }

    public function commit(User $actor, InfoImportBatch $batch): void
    {
        Gate::forUser($actor)->authorize('info.import');
        DB::transaction(function () use ($actor, $batch) {
            $batch = InfoImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (in_array($batch->status, ['processing', 'completed'], true)) {
                return;
            }
            abort_unless(in_array($batch->status, ['previewed', 'failed'], true), 409, 'Preview this import first.');
            $batch->rows()->where('processing_state', 'pending')->where('classification', 'review')->update(['classification' => 'new']);
            $this->summarize($batch);
            $batch->update(['status' => 'processing', 'failure_message' => null]);
            app(Audit::class)->record('info_com', 'import.committed', $batch, $actor);
            CommitInfoImport::dispatch($batch->id, $actor->id)->onQueue('imports')->beforeCommit();
        });
    }

    public function summarize(InfoImportBatch $batch): void
    {
        $states = $batch->rows()->selectRaw('processing_state, COUNT(*) AS total')->groupBy('processing_state')->pluck('total', 'processing_state');
        $batch->update([
            'rows_total' => $batch->rows()->count(), 'rows_valid' => $batch->rows()->whereIn('classification', ['new', 'match'])->count(),
            'rows_invalid' => (int) ($states['invalid'] ?? 0), 'rows_review' => $batch->rows()->where('classification', 'review')->where('processing_state', 'pending')->count(),
            'rows_processed' => $batch->rows()->where('processing_state', '!=', 'pending')->count(),
            'rows_created' => (int) ($states['created'] ?? 0), 'rows_matched' => (int) ($states['matched'] ?? 0),
            'rows_skipped' => (int) ($states['duplicate'] ?? 0) + (int) ($states['skipped'] ?? 0),
            'auto_attendance_created' => InfoAttendanceRecord::whereIn('import_row_id', $batch->rows()->select('id'))->count(),
        ]);
    }
}
