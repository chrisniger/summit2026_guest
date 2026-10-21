<?php

namespace App\Http\Controllers;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\User;
use App\Services\Audit;
use App\Services\CsvExport;
use App\Services\FollowUpReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FollowUpReportController extends Controller
{
    public function index(Request $request, FollowUpReports $reports): View
    {
        Gate::authorize('follow-up.reports');
        $filters = $reports->filters($request->all());
        $actor = $request->user();

        return view('guest.reports', ['filters' => $filters, 'stats' => $reports->summary($actor, $filters),
            'events' => ConferenceEvent::orderByDesc('year')->get(),
            'sessions' => ConferenceSession::whereHas('event', fn ($q) => $q->where('year', $filters['year'] ?? 2026))->orderBy('date_local')->orderBy('id')->get(),
            'officerOptions' => $reports->isManager($actor) ? User::whereHas('roles', fn ($q) => $q->where('name', 'officer'))->orderBy('name')->get() : collect([$actor]),
            'performance' => $reports->officers($actor, $filters), 'breakdown' => $reports->sessionBreakdown($actor, $filters),
            'methods' => $reports->activities($actor, $filters)->selectRaw('method, COUNT(*) AS total')->groupBy('method')->pluck('total', 'method'),
            'guests' => $reports->base($actor, $filters)->with('session', 'activeAssignment.officer')->orderByDesc('id')->paginate(25)->withQueryString(),
            'activities' => $reports->activities($actor, $filters)->with('guest.session', 'actor')->orderByDesc('attempted_at_utc')->orderByDesc('id')->paginate(25, ['*'], 'activity_page')->withQueryString()]);
    }

    public function export(Request $request, FollowUpReports $reports): StreamedResponse
    {
        Gate::authorize('follow-up.reports');
        $filters = $reports->filters($request->all());
        $type = $request->validate(['type' => 'required|in:guests,assignments,status,officers,activities'])['type'];
        $actor = $request->user();
        $rows = [];
        $headers = [];
        if (in_array($type, ['guests', 'assignments'], true)) {
            $headers = ['Guest ID', 'Full name', 'Phone', 'Encounter date', 'Session', 'Current status', 'Current officer', 'Assigned at (WAT)'];
            $rows = (function () use ($reports, $actor, $filters) {
                foreach ($reports->base($actor, $filters)->with('session', 'activeAssignment.officer')->orderBy('id')->cursor() as $guest) {
                    yield [$guest->id, $guest->full_name, $guest->phone_raw, $guest->session->date_local->format('Y-m-d'), $guest->session->label,
                        $guest->current_status, $guest->activeAssignment?->officer?->name ?? 'UNASSIGNED',
                        $guest->activeAssignment?->assigned_at?->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s')];
                }
            })();
        } elseif ($type === 'status') {
            $headers = ['Metric', 'Value'];
            $rows = collect($reports->summary($actor, $filters))->map(fn ($value, $key) => [$key, $value ?? 'n/a'])->values()->all();
        } elseif ($type === 'officers') {
            $headers = ['Officer ID', 'Officer', 'Active', 'Currently assigned', 'Current NOT CONTACTED', 'Current CONTACTED', 'Current UNREACHABLE', 'Current WRONG NUMBER', 'Attempts', 'Distinct attempted', 'Distinct successful', 'Success %', 'Untouched in current assignment and activity period', 'Last activity (UTC)'];
            $rows = $reports->officers($actor, $filters)->map(fn ($row) => [$row['id'], $row['name'], $row['active'] ? 'Yes' : 'No', $row['assigned'],
                $row['not_contacted'], $row['contacted'], $row['unreachable'], $row['wrong_number'], $row['attempts'], $row['attempted_guests'], $row['successful_guests'],
                $row['success_rate'] ?? 'n/a', $row['untouched'], $row['last_activity']])->all();
        } else {
            $headers = ['Activity ID', 'Guest ID', 'Guest', 'Encounter date', 'Session', 'Actor', 'Method', 'Attempt time (WAT)', 'Outcome', 'Status before entry', 'Status after entry', 'Comment'];
            $rows = (function () use ($reports, $actor, $filters) {
                foreach ($reports->activities($actor, $filters)->with('guest.session', 'actor')->orderBy('id')->cursor() as $activity) {
                    yield [$activity->id, $activity->guest_record_id, $activity->guest->full_name, $activity->guest->session->date_local->format('Y-m-d'),
                        $activity->guest->session->label, $activity->actor->name, $activity->method,
                        $activity->attempted_at_utc->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s'), $activity->outcome,
                        $activity->previous_status, $activity->new_status, $activity->comment];
                }
            })();
        }
        app(Audit::class)->record('follow_up', 'report.exported', $actor, $actor, [], ['source' => $type]);

        return CsvExport::download('follow-up-'.$type.'-'.now()->format('Ymd-His').'.csv', $headers, $rows);
    }
}
