<?php

namespace App\Services;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Models\InfoRegistrationEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InfoAttendance
{
    public function lockConference(): void
    {
        ConferenceEvent::where('year', 2026)->lockForUpdate()->firstOrFail();
    }

    public function manual(User $actor, int $attendeeId, int $expectedSessionId): array
    {
        Gate::forUser($actor)->authorize('info.attendance.mark');

        return DB::transaction(function () use ($actor, $attendeeId, $expectedSessionId) {
            $this->lockConference();
            $attendee = InfoAttendee::whereKey($attendeeId)->lockForUpdate()->firstOrFail();
            $session = app(SessionSchedule::class)->activeAt(now());
            abort_unless($session && $session->id === $expectedSessionId && $session->event->year === 2026, 409, 'Session is no longer active. Refresh and try again.');

            return $this->record($actor, $attendee, $session, now(), 'manual');
        }, 3);
    }

    public function record(User $actor, InfoAttendee $attendee, ConferenceSession $session, CarbonInterface $instant, string $source, ?InfoRegistrationEvent $event = null, ?int $rowId = null): array
    {
        Gate::forUser($actor)->authorize($source === 'csv_auto' ? 'info.import' : 'info.attendance.mark');
        $existing = InfoAttendanceRecord::where('info_attendee_id', $attendee->id)->where('conference_session_id', $session->id)->first();
        if ($existing) {
            abort_if($existing->voided_at !== null, 409, 'This attendance was voided. A Super Admin must restore it with a reason.');

            return ['record' => $existing, 'already_present' => true];
        }
        $record = InfoAttendanceRecord::create([
            'info_attendee_id' => $attendee->id, 'conference_session_id' => $session->id,
            'checked_in_at_utc' => $instant->copy()->utc(), 'source' => $source,
            'marked_by_user_id' => $actor->id, 'registration_event_id' => $event?->id, 'import_row_id' => $rowId,
        ]);
        app(Audit::class)->record('info_com', 'attendance.marked', $record, $actor, [], ['conference_session_id' => $session->id, 'source' => $source]);

        return ['record' => $record, 'already_present' => false];
    }

    public function correct(User $actor, InfoAttendanceRecord $record, string $action, string $reason): void
    {
        Gate::forUser($actor)->authorize('platform.manage');
        DB::transaction(function () use ($actor, $record, $action, $reason) {
            $this->lockConference();
            $locked = InfoAttendanceRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['voided_at', 'void_reason']);
            $locked->update(['voided_at' => $action === 'void' ? now() : null, 'void_reason' => $reason]);
            app(Audit::class)->record('info_com', 'attendance.'.$action, $locked, $actor, $before, ['reason' => $reason, 'conference_session_id' => $locked->conference_session_id]);
        }, 3);
    }
}
