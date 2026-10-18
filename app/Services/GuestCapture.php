<?php

namespace App\Services;

use App\Http\Requests\GuestRequest;
use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuestCapture
{
    public function candidates(User $actor, array $data): Builder
    {
        return GuestRecord::visibleTo($actor)->whereNull('archived_at')->where(function (Builder $query) use ($data): void {
            $query->where('phone_normalized', PhoneNumber::normalize($data['phone_raw'] ?? ''))
                ->orWhere('full_name', trim($data['full_name'] ?? ''));
        });
    }

    public function lockEvent(int $sessionId): ConferenceSession
    {
        $session = ConferenceSession::findOrFail($sessionId);
        ConferenceEvent::whereKey($session->conference_event_id)->lockForUpdate()->firstOrFail();

        return $session;
    }

    public function create(User $actor, array $input, string $submissionId, bool $keepSeparate = false): GuestRecord
    {
        Gate::forUser($actor)->authorize('create', GuestRecord::class);
        Validator::make(['submission_id' => $submissionId], ['submission_id' => 'required|uuid'])->validate();
        $data = Validator::make($input, GuestRequest::fields())->validate();

        return DB::transaction(function () use ($actor, $data, $submissionId, $keepSeparate): GuestRecord {
            $this->lockEvent((int) $data['conference_session_id']);
            $fingerprint = hash('sha256', 'guest-entry:'.$actor->id.':'.$submissionId);
            $existing = GuestRecord::where('source_fingerprint', $fingerprint)->lockForUpdate()->first();
            if ($existing) {
                return GuestRecord::visibleTo($actor)->whereKey($existing->id)->lockForUpdate()->firstOrFail();
            }
            if (! $keepSeparate && $this->candidates($actor, $data)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['keep_separate' => 'A guest with this phone or name is visible in your Guest Service records. Review the candidates, then confirm a separate encounter if appropriate.']);
            }

            return $this->insert($actor, $data, $fingerprint);
        }, 3);
    }

    public function insert(User $actor, array $data, string $fingerprint, ?int $importRowId = null, array $raw = []): GuestRecord
    {
        Gate::forUser($actor)->authorize('create', GuestRecord::class);
        $profile = Validator::make($data, GuestRequest::fields())->validate();
        $guest = new GuestRecord([...$profile, 'phone_normalized' => PhoneNumber::normalize($profile['phone_raw']),
            'created_by_user_id' => $actor->id, 'import_row_id' => $importRowId,
            'raw_source_json' => $raw ?: null]);
        if (! empty($profile['captured_at_original_utc'])) {
            $guest->captured_at_original_utc = CarbonImmutable::parse($profile['captured_at_original_utc'])->utc();
        }
        $guest->forceFill(['source_fingerprint' => $fingerprint, 'legacy_officer_name' => $data['_legacy_officer'] ?? null])->save();
        app(Audit::class)->record('follow_up', 'guest.created', $guest, $actor, [], ['conference_session_id' => $guest->conference_session_id]);

        return $guest;
    }

    public function update(User $actor, GuestRecord $guest, array $input, int $version, bool $keepSeparate): GuestRecord
    {
        Gate::forUser($actor)->authorize('update', $guest);
        $data = Validator::make($input, GuestRequest::fields())->validate();

        return DB::transaction(function () use ($actor, $guest, $data, $version, $keepSeparate): GuestRecord {
            $guest = GuestRecord::whereKey($guest->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $guest);
            abort_unless($guest->version === $version, 409, 'This guest was changed by another operator. Reload before editing.');
            if (! $keepSeparate && $this->candidates($actor, $data)->whereKeyNot($guest->id)->exists()) {
                throw ValidationException::withMessages(['keep_separate' => 'Review the matching Guest Service records and confirm that this remains a separate encounter.']);
            }
            $guest->fill([...$data, 'phone_normalized' => PhoneNumber::normalize($data['phone_raw'])]);
            if (! empty($data['captured_at_original_utc'])) {
                $guest->captured_at_original_utc = CarbonImmutable::parse($data['captured_at_original_utc'])->utc();
            }
            $changed = array_keys($guest->getDirty());
            $guest->forceFill(['version' => $guest->version + 1])->save();
            app(Audit::class)->record('follow_up', 'guest.updated', $guest, $actor, [], ['changed_fields' => $changed]);

            return $guest;
        }, 3);
    }
}
