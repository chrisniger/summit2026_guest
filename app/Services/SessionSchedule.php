<?php

namespace App\Services;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
