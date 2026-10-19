<?php

namespace App\Http\Controllers;

use App\Models\InfoAttendee;
use App\Services\InfoAttendance;
use App\Services\InfoRegistration;
use App\Services\PhoneNumber;
use App\Services\SessionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicAttendanceController extends Controller
{
    public function show(SessionSchedule $schedule): View
    {
        return view('info.public-attendance', ['session' => $schedule->activeAt(now())]);
    }

    public function store(Request $request, SessionSchedule $schedule, InfoRegistration $registration, InfoAttendance $attendance): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_raw' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail) {
                if (! PhoneNumber::normalize($value)) {
                    $fail('Enter a valid phone number containing 7–15 digits.');
                }
            }],
            'submission_id' => 'required|uuid',
        ]);

        $session = $schedule->activeAt(now());
        if (! $session) {
            return back()->withInput()->with('attendance_error', 'Check-in is not open right now. Please see the Info Com desk.');
        }

        $name = trim($data['first_name']).' '.trim($data['last_name']);
        $attendee = $this->matchAttendee($data['phone_raw']);

        if (! $attendee) {
            $attendee = $registration->register(
                null,
                [...collect($data)->except('submission_id')->all(), 'attendance_preference' => 'In Person'],
                now(),
                hash('sha256', 'self-service:'.$data['submission_id']),
                'other'
            )['attendee'];
        }

        $attendance->selfService($attendee, $session, now());

        return redirect()->route('attendance.public')->with('status', 'Welcome to SUMMIT 2026, '.$name.'. You are marked present.');
    }

    private function matchAttendee(string $phone): ?InfoAttendee
    {
        $normalized = PhoneNumber::normalize($phone);
        $lastSixDigits = substr(preg_replace('/\D/', '', (string) $normalized), -6);

        return InfoAttendee::where('phone_normalized', $normalized)->latest('id')->first()
            ?? InfoAttendee::where('phone_normalized', 'like', '%'.$lastSixDigits)->latest('id')->first();
    }
}
