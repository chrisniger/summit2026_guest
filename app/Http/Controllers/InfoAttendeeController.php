<?php

namespace App\Http\Controllers;

use App\Http\Requests\InfoAttendeeRequest;
use App\Models\InfoAttendee;
use App\Services\Audit;
use App\Services\InfoRegistration;
use App\Services\PhoneNumber;
use App\Services\SessionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InfoAttendeeController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', InfoAttendee::class);

        return view('info.attendee-form', ['attendee' => null, 'submissionId' => Str::uuid()]);
    }

    public function store(InfoAttendeeRequest $request, InfoRegistration $registration): RedirectResponse
    {
        $data = $request->validated();
        $token = $data['submission_id'];
        unset($data['submission_id']);
        $result = $registration->register($request->user(), $data, now(), hash('sha256', 'onsite:'.$request->user()->id.':'.$token));

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

    public function update(InfoAttendeeRequest $request, InfoAttendee $attendee): RedirectResponse
    {
        Gate::authorize('update', $attendee);
        $data = $request->safe()->except('submission_id');
        DB::transaction(function () use ($data, $attendee, $request) {
            $attendee->fill([...$data, 'phone_normalized' => PhoneNumber::normalize($data['phone_raw'])]);
            $changed = array_keys($attendee->getDirty());
            $attendee->save();
            app(Audit::class)->record('info_com', 'attendee.updated', $attendee, $request->user(), [], ['changed_fields' => $changed]);
        });

        return redirect()->route('info.attendees.show', $attendee)->with('status', 'Attendee details updated. Attendance history is unchanged.');
    }
}
