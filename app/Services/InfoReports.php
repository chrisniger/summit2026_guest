<?php

namespace App\Services;

use App\Models\ConferenceSession;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class InfoReports
{
    public function base(array $filters): Builder
    {
        $query = InfoAttendee::whereRaw('YEAR(COALESCE(registration_original_at_utc, created_at)) = ?', [2026])->search(trim($filters['search'] ?? ''));
        foreach (['registration_source', 'attendance_preference', 'member', 'first_time'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($key, $filters[$key]);
            }
        }

        return $query;
    }

    public function cutoff(ConferenceSession $session): CarbonImmutable
    {
        return $session->ends_at_utc && $session->ends_at_utc->lt(now()) ? $session->ends_at_utc : CarbonImmutable::now();
    }

    public function eligible(Builder $base, ConferenceSession $session): Builder
    {
        return (clone $base)->whereRaw('COALESCE(registration_original_at_utc, created_at) <= ?', [$this->cutoff($session)]);
    }

    public function build(array $filters): array
    {
        $sessions = ConferenceSession::currentConference()->orderBy('id')->get();
        $selected = ! empty($filters['session_id']) ? $sessions->firstWhere('id', (int) $filters['session_id']) : null;
        if (! empty($filters['session_id']) && ! $selected) {
            abort(404);
        }
        if ($selected && ! empty($filters['day']) && (int) $filters['day'] !== $selected->day_number) {
            throw ValidationException::withMessages(['session_id' => 'Choose a session from the selected day, or clear the day filter.']);
        }
        $included = $sessions->filter(fn ($session) => (! $selected || $session->id === $selected->id) && (empty($filters['day']) || $session->day_number === (int) $filters['day']));
        $base = $this->base($filters);
        $list = clone $base;
        if (in_array($filters['status'] ?? 'all', ['present', 'absent'], true)) {
            if (! $selected || ! $selected->ends_at_utc) {
                throw ValidationException::withMessages(['session_id' => 'Select a session with configured times to filter present/absent records.']);
            }
            $list = $this->eligible($list, $selected);
            $presence = fn ($query) => $query->whereNull('voided_at')->where('conference_session_id', $selected->id);
            ($filters['status'] === 'present') ? $list->whereHas('attendanceRecords', $presence) : $list->whereDoesntHave('attendanceRecords', $presence);
        }
        $summary = [];
        foreach ($included as $session) {
            $eligible = $this->eligible($base, $session);
            $present = (clone $eligible)->whereHas('attendanceRecords', fn ($query) => $query->where('conference_session_id', $session->id)->whereNull('voided_at'))->count();
            $count = $eligible->count();
            $summary[] = ['session' => $session, 'eligible' => $count, 'present' => $present,
                'absent' => $session->ends_at_utc ? $count - $present : null,
                'rate' => $session->ends_at_utc && $count > 0 ? round(100 * $present / $count, 1) : null,
                'cutoff' => $this->cutoff($session)];
        }
        $sessionIds = $included->pluck('id');
        $checks = InfoAttendanceRecord::whereNull('voided_at')->whereIn('conference_session_id', $sessionIds)->whereIn('info_attendee_id', (clone $base)->select('id'));
        $days = [];
        foreach ($included->groupBy('day_number') as $day => $group) {
            $days[$day] = (clone $base)->whereHas('attendanceRecords', fn ($query) => $query->whereNull('voided_at')->whereIn('conference_session_id', $group->pluck('id')))->count();
        }
        $breakdowns = [];
        foreach (['attendance_preference', 'member', 'first_time', 'registration_source', 'heard_about'] as $field) {
            $breakdowns[$field] = (clone $base)->selectRaw($field.' AS category, COUNT(*) AS total')->groupBy($field)->orderByDesc('total')->limit(20)->get();
        }

        return ['sessions' => $sessions, 'selected' => $selected, 'summary' => $summary, 'days' => $days, 'breakdowns' => $breakdowns,
            'registered' => (clone $base)->count(), 'unique_present' => (clone $checks)->distinct()->count('info_attendee_id'),
            'check_ins' => $checks->count(), 'list' => $list, 'filters' => $filters, 'as_of' => now()];
    }
}
