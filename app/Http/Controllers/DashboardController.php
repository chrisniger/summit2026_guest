<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Services\FollowUpReports;
use App\Services\InfoReports;
use App\Services\SessionSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(Request $request, SessionSchedule $schedule): View
    {
        $workspace = $request->route()->getName();
        if ($workspace === 'info') {
            return view('info.check-in');
        }
        $sessions = ConferenceSession::currentConference()->orderBy('date_local')->orderBy('id')->get();
        $info = null;
        $guests = null;
        if (in_array($workspace, ['admin', 'info'], true)) {
            $cohort = app(InfoReports::class)->base([]);
            $info = ['registered' => (clone $cohort)->count(), 'check_ins' => InfoAttendanceRecord::whereNull('voided_at')
                ->whereIn('info_attendee_id', (clone $cohort)->select('id'))
                ->whereHas('session', fn ($q) => $q->currentConference())->count()];
        }
        if ($workspace !== 'info') {
            $scope = GuestRecord::visibleTo($request->user())->whereNull('archived_at')->whereHas('session', fn ($q) => $q->currentConference());
            if ($workspace === 'guest-entry') {
                $scope->where('created_by_user_id', $request->user()->id);
            }
            if ($workspace === 'my-guests') {
                $scope->whereHas('activeAssignment', fn ($query) => $query->where('officer_user_id', $request->user()->id));
            }
            $guests = [
                'total' => (clone $scope)->count(),
                'unassigned' => (clone $scope)->doesntHave('activeAssignment')->count(),
                'contacted' => (clone $scope)->where('current_status', 'CONTACTED')->count(),
            ];
        }

        $followUp = null;
        $assignedGuests = collect();
        if ($request->user()->can('follow-up.reports')) {
            $filters = $workspace === 'my-guests' ? ['officer' => $request->user()->id] : [];
            $reports = app(FollowUpReports::class);
            $followUp = $reports->summary($request->user(), $filters);
            if ($workspace === 'my-guests') {
                $assignedGuests = $reports->base($request->user(), $filters)->with('session')->orderBy('id')->limit(10)->get();
            }
        }

        return view('dashboard', compact('workspace', 'sessions', 'info', 'guests', 'followUp', 'assignedGuests') + ['currentSession' => $schedule->activeAt(now())]);
    }
}
