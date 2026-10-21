<?php

namespace App\Services;

use App\Models\ConferenceSession;
use App\Models\FollowUpActivity;
use App\Models\FollowUpAssignment;
use App\Models\GuestRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class FollowUpReports
{
    public function filters(array $input): array
    {
        return Validator::make($input, [
            'search' => 'nullable|string|max:255', 'year' => 'nullable|integer|min:2000|max:2100',
            'session' => 'nullable|integer|exists:conference_sessions,id', 'day' => 'nullable|integer|min:1|max:31',
            'status' => 'nullable|in:NOT CONTACTED,CONTACTED,UNREACHABLE,WRONG NUMBER',
            'assignment' => 'nullable|in:assigned,unassigned', 'officer' => 'nullable|integer|exists:users,id',
            'source' => 'nullable|in:manual,import', 'archive' => 'nullable|in:active,archived',
            'encounter_from' => 'nullable|date_format:Y-m-d', 'encounter_to' => 'nullable|date_format:Y-m-d'.(! empty($input['encounter_from']) ? '|after_or_equal:encounter_from' : ''),
            'activity_from' => 'nullable|date_format:Y-m-d', 'activity_to' => 'nullable|date_format:Y-m-d'.(! empty($input['activity_from']) ? '|after_or_equal:activity_from' : ''),
            'activity_actor' => 'nullable|integer|exists:users,id',
        ])->validate();
    }

    public function base(User $actor, array $filters): Builder
    {
        $query = GuestRecord::visibleTo($actor)->whereHas('session.event', fn ($q) => $q->where('year', $filters['year'] ?? 2026));
        ($filters['archive'] ?? 'active') === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        foreach (['session' => 'conference_session_id', 'status' => 'current_status'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        if (! empty($filters['search'])) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', '%'.$search.'%');
                if (preg_match('/^[+0-9 ().-]+$/', $search)) {
                    $q->orWhere('phone_normalized', 'like', PhoneNumber::searchPrefix($search).'%');
                }
            });
        }
        if (! empty($filters['officer'])) {
            $query->whereHas('activeAssignment', fn ($q) => $q->where('officer_user_id', $filters['officer']));
        }
        if (! empty($filters['assignment'])) {
            $filters['assignment'] === 'assigned' ? $query->has('activeAssignment') : $query->doesntHave('activeAssignment');
        }
        if (! empty($filters['source'])) {
            $filters['source'] === 'import' ? $query->whereNotNull('import_row_id') : $query->whereNull('import_row_id');
        }
        $query->whereHas('session', function ($q) use ($filters): void {
            if (! empty($filters['day'])) {
                $q->where('day_number', $filters['day']);
            }
            if (! empty($filters['encounter_from'])) {
                $q->where('date_local', '>=', $filters['encounter_from']);
            }
            if (! empty($filters['encounter_to'])) {
                $q->where('date_local', '<=', $filters['encounter_to']);
            }
        });

        return $query;
    }

    public function isManager(User $actor): bool
    {
        return $actor->hasPermission('guest.report.view') || $actor->hasPermission('guest.assign');
    }

    public function activities(User $actor, array $filters, ?Builder $cohort = null): Builder
    {
        $query = FollowUpActivity::whereIn('guest_record_id', ($cohort ?? $this->base($actor, $filters))->select('guest_records.id'));
        if (! $this->isManager($actor)) {
            $query->where('actor_user_id', $actor->id)->whereHas('assignment', fn ($q) => $q->whereNull('unassigned_at')->where('officer_user_id', $actor->id));
        }
        if (! empty($filters['activity_actor'])) {
            $query->where('actor_user_id', $filters['activity_actor']);
        }
        foreach (['activity_from' => '>=', 'activity_to' => '<'] as $key => $operator) {
            if (! empty($filters[$key])) {
                $boundary = CarbonImmutable::createFromFormat('!Y-m-d', $filters[$key], 'Africa/Lagos');
                if ($key === 'activity_to') {
                    $boundary = $boundary->addDay();
                }
                $query->where('attempted_at_utc', $operator, $boundary->utc());
            }
        }

        return $query;
    }

    public function summary(User $actor, array $filters): array
    {
        Gate::forUser($actor)->authorize('follow-up.reports');
        $cohort = $this->base($actor, $filters);
        $activity = $this->activities($actor, $filters, clone $cohort);
        $attempts = (clone $activity)->where('outcome', '!=', 'NOTE_ONLY');
        $attempted = (clone $attempts)->distinct()->count('guest_record_id');
        $successful = (clone $attempts)->where('outcome', 'CONTACTED')->distinct()->count('guest_record_id');
        $statuses = (clone $cohort)->selectRaw('current_status, COUNT(*) AS total')->groupBy('current_status')->pluck('total', 'current_status');

        return ['total' => (clone $cohort)->count(), 'assigned' => (clone $cohort)->has('activeAssignment')->count(),
            'unassigned' => (clone $cohort)->doesntHave('activeAssignment')->count(),
            'not_contacted' => (int) ($statuses['NOT CONTACTED'] ?? 0), 'contacted' => (int) ($statuses['CONTACTED'] ?? 0),
            'unreachable' => (int) ($statuses['UNREACHABLE'] ?? 0), 'wrong_number' => (int) ($statuses['WRONG NUMBER'] ?? 0),
            'attempts' => (clone $attempts)->count(),
            'attempted_guests' => $attempted, 'successful_guests' => $successful, 'notes' => (clone $activity)->where('outcome', 'NOTE_ONLY')->count(),
            'success_rate' => $attempted ? round(100 * $successful / $attempted, 1) : null];
    }

    public function sessionBreakdown(User $actor, array $filters): Collection
    {
        $ids = $this->base($actor, $filters)->select('guest_records.id');

        return ConferenceSession::whereHas('event', fn ($q) => $q->where('year', $filters['year'] ?? 2026))
            ->withCount(['guests as guest_total' => fn ($q) => $q->whereIn('guest_records.id', clone $ids),
                'guests as unassigned_total' => fn ($q) => $q->whereIn('guest_records.id', clone $ids)->doesntHave('activeAssignment')])
            ->orderBy('date_local')->orderBy('id')->get();
    }

    public function officers(User $actor, array $filters): Collection
    {
        $cohort = $this->base($actor, $filters);
        $activity = $this->activities($actor, $filters, clone $cohort);
        $workload = FollowUpAssignment::whereNull('unassigned_at')->whereIn('guest_record_id', (clone $cohort)->select('guest_records.id'))
            ->selectRaw('officer_user_id, COUNT(*) AS total')->groupBy('officer_user_id')->pluck('total', 'officer_user_id');
        $activityTotals = (clone $activity)->selectRaw("actor_user_id, COUNT(*) AS activities, SUM(outcome <> 'NOTE_ONLY') AS attempts, COUNT(DISTINCT CASE WHEN outcome <> 'NOTE_ONLY' THEN guest_record_id END) AS attempted_guests, COUNT(DISTINCT CASE WHEN outcome = 'CONTACTED' THEN guest_record_id END) AS successful_guests, MAX(attempted_at_utc) AS last_activity")
            ->groupBy('actor_user_id')->get()->keyBy('actor_user_id');
        $users = User::query();
        if ($this->isManager($actor)) {
            $users->where(function ($q) use ($workload, $activityTotals): void {
                $q->whereHas('roles', fn ($roles) => $roles->where('name', 'officer'))
                    ->orWhereIn('id', $workload->keys())->orWhereIn('id', $activityTotals->keys());
            });
        } else {
            $users->whereKey($actor->id);
        }

        return $users->orderBy('name')->get()->map(function (User $officer) use ($cohort, $workload, $activityTotals, $activity): array {
            $own = (clone $cohort)->whereHas('activeAssignment', fn ($q) => $q->where('officer_user_id', $officer->id));
            $status = (clone $own)->selectRaw('current_status, COUNT(*) AS total')->groupBy('current_status')->pluck('total', 'current_status');
            $totals = $activityTotals->get($officer->id);
            $attempted = (int) ($totals?->attempted_guests ?? 0);
            $successful = (int) ($totals?->successful_guests ?? 0);
            $currentAttempts = (clone $activity)->where('actor_user_id', $officer->id)->where('outcome', '!=', 'NOTE_ONLY')
                ->whereHas('assignment', fn ($q) => $q->whereNull('unassigned_at')->where('officer_user_id', $officer->id));
            $untouched = (clone $own)->whereNotIn('guest_records.id', $currentAttempts->select('guest_record_id'))->count();

            return ['id' => $officer->id, 'name' => $officer->name, 'active' => $officer->is_active,
                'assigned' => (int) ($workload[$officer->id] ?? 0), 'not_contacted' => (int) ($status['NOT CONTACTED'] ?? 0),
                'contacted' => (int) ($status['CONTACTED'] ?? 0), 'unreachable' => (int) ($status['UNREACHABLE'] ?? 0),
                'wrong_number' => (int) ($status['WRONG NUMBER'] ?? 0),
                'attempts' => (int) ($totals?->attempts ?? 0), 'attempted_guests' => $attempted, 'successful_guests' => $successful,
                'success_rate' => $attempted ? round(100 * $successful / $attempted, 1) : null,
                'untouched' => $untouched, 'last_activity' => $totals?->last_activity];
        });
    }

    public function guestActivities(User $actor, GuestRecord $guest): Builder
    {
        return $this->activities($actor, [], GuestRecord::visibleTo($actor)->whereKey($guest->id))
            ->with('actor')->orderByDesc('attempted_at_utc')->orderByDesc('id');
    }
}
