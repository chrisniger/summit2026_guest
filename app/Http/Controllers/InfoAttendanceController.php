<?php

namespace App\Http\Controllers;

use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Services\InfoAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InfoAttendanceController extends Controller
{
    public function store(Request $request, InfoAttendee $attendee, InfoAttendance $attendance): RedirectResponse
    {
        $data = $request->validate(['conference_session_id' => 'required|integer|exists:conference_sessions,id']);
        $result = $attendance->manual($request->user(), $attendee->id, (int) $data['conference_session_id']);

        return back()->with('status', $result['already_present'] ? 'Already present — no duplicate check-in.' : 'Present — check-in saved.');
    }

    public function correct(Request $request, InfoAttendanceRecord $record, InfoAttendance $attendance): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:void,restore', 'reason' => 'required|string|min:5|max:1000']);
        $attendance->correct($request->user(), $record, $data['action'], $data['reason']);

        return back()->with('status', 'Attendance '.$data['action'].' saved with an audit reason.');
    }
}
