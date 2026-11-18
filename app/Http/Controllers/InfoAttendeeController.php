<?php

namespace App\Http\Controllers;

use App\Http\Requests\InfoAttendeeRequest;
use App\Models\ConferenceSession;
use App\Models\InfoAttendee;
use App\Services\Audit;
use App\Services\InfoAttendance;
use App\Services\InfoAttendeeMatcher;
use App\Services\InfoRegistration;
use App\Services\PhoneNumber;
use App\Services\SessionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InfoAttendeeController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', InfoAttendee::class);

        return view('info.attendee-form', ['attendee' => null, 'submissionId' => Str::uuid(),
            'registrationSessions' => ConferenceSession::currentConference()->whereNull('attendance_locked_at')
                ->whereDate('date_local', '<=', now(config('summit.timezone'))->toDateString())
                ->where(fn ($query) => $query->whereNull('starts_at_utc')->orWhere('starts_at_utc', '<=', now()))
                ->orderByDesc('date_local')->orderBy('id')->get()]);
    }

    public function store(InfoAttendeeRequest $request, InfoRegistration $registration): RedirectResponse
    {
        $data = $request->validated();
        $token = $data['submission_id'];
        $attendanceSessionId = isset($data['attendance_session_id']) ? (int) $data['attendance_session_id'] : null;
        unset($data['submission_id'], $data['other_session'], $data['attendance_session_id']);
        $result = $registration->register($request->user(), $data, now(), hash('sha256', 'onsite:'.$request->user()->id.':'.$token), attendanceSessionId: $attendanceSessionId);

        return redirect()->route('info.attendees.show', $result['attendee'])->with('status', 'Registration saved. '.$result['reason']);
    }

    public function show(InfoAttendee $attendee): View
    {
        Gate::authorize('view', $attendee);

        return view('info.attendee-detail', ['attendee' => $attendee->load(['registrationEvents', 'attendanceRecords.session']), 'currentSession' => app(SessionSchedule::class)->activeAt(now())]);
    }

    public function edit(InfoAttendee $attendee): View
    {
        Gate::authorize('update', $attendee);

        return view('info.attendee-form', ['attendee' => $attendee, 'submissionId' => Str::uuid()]);
    }

    public function update(InfoAttendeeRequest $request, InfoAttendee $attendee, InfoAttendeeMatcher $attendeeMatcher, InfoAttendance $attendance): RedirectResponse
    {
        Gate::authorize('update', $attendee);
        $data = $request->safe()->except('submission_id');

        DB::transaction(function () use ($data, $attendee, $request, $attendeeMatcher, $attendance) {
            $attendance->lockConference();

            if ($attendeeMatcher->findExactMatch($data, $attendee->id)) {
                throw ValidationException::withMessages(['phone_raw' => 'Another attendee already has this exact name and phone number. Use the existing attendee instead.']);
            }

            $attendee->fill([...$data, 'phone_normalized' => PhoneNumber::normalize($data['phone_raw'])]);
            $changed = array_keys($attendee->getDirty());
            $attendee->save();
            app(Audit::class)->record('info_com', 'attendee.updated', $attendee, $request->user(), [], ['changed_fields' => $changed]);
        });

        return redirect()->route('info.attendees.show', $attendee)->with('status', 'Attendee details updated. Attendance history is unchanged.');
    }
}
