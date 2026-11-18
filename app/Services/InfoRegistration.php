<?php

namespace App\Services;

use App\Models\ConferenceSession;
use App\Models\InfoAttendee;
use App\Models\InfoRegistrationEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InfoRegistration
{
    public function __construct(private InfoAttendeeMatcher $attendeeMatcher) {}

    public function register(?User $actor, array $data, CarbonInterface $instant, string $fingerprint, string $source = 'onsite', ?int $rowId = null, ?int $existingId = null, ?int $attendanceSessionId = null): array
    {
        if ($actor) {
            Gate::forUser($actor)->authorize($source === 'csv' ? 'info.import' : 'info.attendee.create');
        } else {
            abort_unless($source === 'other', 403);
        }
        if ($attendanceSessionId !== null) {
            abort_unless($actor && $source === 'onsite', 403);
            Gate::forUser($actor)->authorize('info.attendance.mark');
        }

        return DB::transaction(function () use ($actor, $data, $instant, $fingerprint, $source, $rowId, $existingId, $attendanceSessionId) {
            $attendance = app(InfoAttendance::class);
            $attendance->lockConference();
            $previous = InfoRegistrationEvent::where('source_fingerprint', $fingerprint)->first();
            if ($previous) {
                return ['attendee' => $previous->attendee, 'attendance' => null, 'reason' => 'Registration already saved.', 'replayed' => true, 'created' => false];
            }
            $selectedSession = null;
            if ($attendanceSessionId !== null) {
                $selectedSession = ConferenceSession::currentConference()->whereKey($attendanceSessionId)->lockForUpdate()->first();
                if (! $selectedSession || $selectedSession->attendance_locked_at !== null
                    || $selectedSession->date_local->format('Y-m-d') > $instant->copy()->setTimezone(config('summit.timezone'))->format('Y-m-d')
                    || $selectedSession->starts_at_utc?->gt($instant)) {
                    throw ValidationException::withMessages(['attendance_session_id' => 'Choose an unlocked 2026 session that has already started. Future sessions cannot be marked present.']);
                }
            }
            $matchedAttendee = $existingId === null ? $this->attendeeMatcher->findExactMatch($data) : null;
            $created = $existingId === null && $matchedAttendee === null;
            $attendee = $existingId
                ? InfoAttendee::whereKey($existingId)->lockForUpdate()->firstOrFail()
                : ($matchedAttendee ?? InfoAttendee::create([...$data, 'phone_normalized' => PhoneNumber::normalize($data['phone_raw']),
                    'registration_original_at_utc' => $instant->copy()->utc(), 'registration_source' => $source, 'created_by_user_id' => $actor?->id]));
            $event = InfoRegistrationEvent::create(['info_attendee_id' => $attendee->id, 'source' => in_array($source, ['onsite', 'csv'], true) ? $source : 'onsite',
                'registered_at_utc' => $instant->copy()->utc(), 'created_by_user_id' => $actor?->id,
                'import_row_id' => $rowId, 'source_fingerprint' => $fingerprint]);
            if (! $created && $instant->lt($attendee->registration_original_at_utc ?? $attendee->created_at)) {
                $attendee->update(['registration_original_at_utc' => $instant->copy()->utc()]);
            }
            $session = $selectedSession ?? app(SessionSchedule::class)->activeAt($instant);
            $result = null;
            $virtual = $selectedSession === null && (($data['attendance_preference'] ?? null) === 'Virtual' || $attendee->attendance_preference === 'Virtual');
            $reason = $virtual ? 'Virtual preference does not prove physical attendance.' : 'No unlocked, enabled session matches the original registration time.';
            if (! $virtual && $session && $session->event->year === 2026) {
                $attendanceSource = $selectedSession ? 'manual' : ($source === 'csv' ? 'csv_auto' : 'registration_auto');
                $result = $attendance->record($actor, $attendee, $session, $instant, $attendanceSource, $event, $rowId);
                $sessionLabel = $session->label.' ('.$session->date_local->format('j M Y').')';
                $reason = $result['already_present'] ? 'Already present for '.$sessionLabel.'.'
                    : ($selectedSession ? 'Marked present for selected session: '.$sessionLabel.'.' : 'Automatically checked in to '.$session->label.'.');
            }
            if ($matchedAttendee !== null) {
                $reason = 'The exact name and phone already exist, so the existing attendee was kept. '.$reason;
            }
            app(Audit::class)->record('info_com', $result ? 'registration.saved' : 'registration.without_attendance', $event, $actor, [], ['source' => $source, 'reason' => $reason]);

            return ['attendee' => $attendee, 'attendance' => $result, 'reason' => $reason, 'replayed' => false, 'created' => $created];
        }, 3);
    }
}
