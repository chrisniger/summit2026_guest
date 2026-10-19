<?php

namespace App\Services;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionSchedule
{
    public function activeAt(CarbonInterface $instant): ?ConferenceSession
    {
        $sessions = ConferenceSession::query()->where('enabled', true)
            ->where('starts_at_utc', '<=', $instant->copy()->utc())
            ->where('ends_at_utc', '>', $instant->copy()->utc())
            ->whereNull('attendance_locked_at')->limit(2)->get();

        return $sessions->count() === 1 ? $sessions->first() : null;
    }

    public function addTestSession(User $actor, string $date, string $start, string $end): ConferenceSession
    {
        Gate::forUser($actor)->authorize('platform.manage');
        $timezone = config('summit.timezone');
        $starts = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$start, $timezone);
        $ends = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$end, $timezone);
        if (! $starts || ! $ends || $starts->format('Y-m-d H:i') !== $date.' '.$start || $ends->format('Y-m-d H:i') !== $date.' '.$end || ! $starts->lt($ends)) {
            throw ValidationException::withMessages(['schedule' => 'Use a valid test date with an end time after the start time.']);
        }
        if (! str_starts_with($date, '2026-')) {
            throw ValidationException::withMessages(['schedule' => 'Test sessions must fall within the 2026 conference year.']);
        }

        return DB::transaction(function () use ($actor, $date, $starts, $ends) {
            $event = ConferenceEvent::where('year', 2026)->lockForUpdate()->firstOrFail();
            if (ConferenceSession::where('enabled', true)
                ->where('starts_at_utc', '<', $ends->utc())->where('ends_at_utc', '>', $starts->utc())->exists()) {
                throw ValidationException::withMessages(['schedule' => 'This test window overlaps an enabled session.']);
            }
            $session = ConferenceSession::create([
                'conference_event_id' => $event->id, 'code' => 'TEST-'.str_replace('-', '', $date).'-'.Str::lower(Str::random(6)),
                'day_number' => 0, 'date_local' => $date, 'name' => 'General', 'label' => 'Test session',
                'starts_at_utc' => $starts->utc(), 'ends_at_utc' => $ends->utc(), 'enabled' => true,
            ]);
            app(Audit::class)->record('platform', 'session.test_created', $session, $actor, [], $session->only(['enabled', 'starts_at_utc', 'ends_at_utc']));

            return $session;
        });
    }

    public function removeTestSession(User $actor, ConferenceSession $session): void
    {
        Gate::forUser($actor)->authorize('platform.manage');
        DB::transaction(function () use ($actor, $session): void {
            $locked = ConferenceSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isTest()) {
                throw ValidationException::withMessages(['test' => 'Only test sessions can be removed here.']);
            }
            if (InfoAttendanceRecord::where('conference_session_id', $locked->id)->exists()
                || GuestRecord::where('conference_session_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['test' => 'This test session already has attendance or guest records. Disable it instead to keep the history.']);
            }
            $before = $locked->only(['enabled', 'starts_at_utc', 'ends_at_utc']);
            $locked->delete();
            app(Audit::class)->record('platform', 'session.test_removed', $locked, $actor, $before, []);
        });
    }

    public function configure(User $actor, ConferenceSession $session, ?string $start, ?string $end, bool $enabled): void
    {
        Gate::forUser($actor)->authorize('platform.manage');
        if (($start === null) !== ($end === null) || ($enabled && $start === null)) {
            throw ValidationException::withMessages(['schedule' => 'Set both start and end times before enabling a session.']);
        }
        $date = $session->date_local->format('Y-m-d');
        $starts = $start ? CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$start, config('summit.timezone')) : null;
        $ends = $end ? CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$end, config('summit.timezone')) : null;
        if ($starts && (! $starts->lt($ends) || $starts->format('Y-m-d H:i') !== $date.' '.$start || $ends->format('Y-m-d H:i') !== $date.' '.$end)) {
            throw ValidationException::withMessages(['schedule' => 'End time must be after start time on the selected conference date.']);
        }
        DB::transaction(function () use ($actor, $session, $starts, $ends, $enabled) {
            ConferenceEvent::whereKey($session->conference_event_id)->lockForUpdate()->firstOrFail();
            $locked = ConferenceSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($enabled && ConferenceSession::where('enabled', true)->where('id', '!=', $session->id)
                ->where('starts_at_utc', '<', $ends->utc())->where('ends_at_utc', '>', $starts->utc())->exists()) {
                throw ValidationException::withMessages(['schedule' => 'This time window overlaps an enabled session.']);
            }
            $before = $locked->only(['enabled', 'starts_at_utc', 'ends_at_utc']);
            $locked->update(['starts_at_utc' => $starts?->utc(), 'ends_at_utc' => $ends?->utc(), 'enabled' => $enabled]);
            app(Audit::class)->record('platform', 'session.configured', $locked, $actor, $before, $locked->only(['enabled', 'starts_at_utc', 'ends_at_utc']));
        });
    }
}
