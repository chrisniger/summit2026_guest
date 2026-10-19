<?php

namespace App\Services;

use App\Models\InfoAttendee;
use App\Models\InfoRegistrationEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InfoRegistration
{
    public function register(?User $actor, array $data, CarbonInterface $instant, string $fingerprint, string $source = 'onsite', ?int $rowId = null, ?int $existingId = null): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize($source === 'csv' ? 'info.import' : 'info.attendee.create');
        } else {
            abort_unless($source === 'other', 403);
        }

        return DB::transaction(function () use ($actor, $data, $instant, $fingerprint, $source, $rowId, $existingId) {
            $attendance = app(InfoAttendance::class);
            $attendance->lockConference();
            $previous = InfoRegistrationEvent::where('source_fingerprint', $fingerprint)->first();
            if ($previous) {
                return ['attendee' => $previous->attendee, 'attendance' => null, 'reason' => 'Registration already saved.', 'replayed' => true, 'created' => false];
            }
            $attendee = $existingId
                ? InfoAttendee::whereKey($existingId)->lockForUpdate()->firstOrFail()
                : InfoAttendee::create([...$data, 'phone_normalized' => PhoneNumber::normalize($data['phone_raw']),
                    'registration_original_at_utc' => $instant->copy()->utc(), 'registration_source' => $source, 'created_by_user_id' => $actor?->id]);
            $event = InfoRegistrationEvent::create(['info_attendee_id' => $attendee->id, 'source' => in_array($source, ['onsite', 'csv'], true) ? $source : 'onsite',
                'registered_at_utc' => $instant->copy()->utc(), 'created_by_user_id' => $actor?->id,
                'import_row_id' => $rowId, 'source_fingerprint' => $fingerprint]);
            if ($existingId && $instant->lt($attendee->registration_original_at_utc ?? $attendee->created_at)) {
                $attendee->update(['registration_original_at_utc' => $instant->copy()->utc()]);
            }
            $session = app(SessionSchedule::class)->activeAt($instant);
            $result = null;
            $virtual = ($data['attendance_preference'] ?? null) === 'Virtual' || $attendee->attendance_preference === 'Virtual';
            $reason = $virtual ? 'Virtual preference does not prove physical attendance.' : 'No unlocked, enabled session matches the original registration time.';
            if (! $virtual && $session && $session->event->year === 2026) {
                $result = $attendance->record($actor, $attendee, $session, $instant, $source === 'csv' ? 'csv_auto' : 'registration_auto', $event, $rowId);
                $reason = $result['already_present'] ? 'Already present for '.$session->label.'.' : 'Automatically checked in to '.$session->label.'.';
            }
            app(Audit::class)->record('info_com', $result ? 'registration.saved' : 'registration.without_attendance', $event, $actor, [], ['source' => $source, 'reason' => $reason]);

            return ['attendee' => $attendee, 'attendance' => $result, 'reason' => $reason, 'replayed' => false, 'created' => $existingId === null];
        }, 3);
    }
}
