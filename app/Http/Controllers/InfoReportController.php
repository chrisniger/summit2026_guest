<?php

namespace App\Http\Controllers;

use App\Models\ConferenceEvent;
use App\Services\Audit;
use App\Services\CsvExport;
use App\Services\InfoReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InfoReportController extends Controller
{
    private function filters(Request $request): array
    {
        return $request->validate(['session_id' => 'nullable|integer|exists:conference_sessions,id', 'day' => 'nullable|integer|between:1,6',
            'search' => 'nullable|string|max:100', 'status' => 'nullable|in:all,present,absent',
            'attendance_preference' => 'nullable|in:In Person,Virtual', 'registration_source' => 'nullable|in:onsite,csv,other',
            'member' => 'nullable|boolean', 'first_time' => 'nullable|boolean']);
    }

    public function index(Request $request, InfoReports $reports): View
    {
        $data = $reports->build($this->filters($request));

        return view('info.reports', $data + ['attendees' => (clone $data['list'])->orderBy('last_name')->orderBy('id')->paginate(25)->withQueryString()]);
    }

    public function export(Request $request, InfoReports $reports): StreamedResponse
    {
        Gate::authorize('platform.manage');
        $data = $reports->build($this->filters($request));
        $type = $request->validate(['format' => 'nullable|in:matrix,summary'])['format'] ?? 'matrix';
        app(Audit::class)->record('info_com', 'report.exported', ConferenceEvent::where('year', 2026)->firstOrFail(), $request->user(), [], ['reason' => $type]);
        if ($type === 'summary') {
            $rows = (function () use ($data) {
                foreach ($data['summary'] as $row) {
                    yield [$row['session']->label, $row['session']->date_local->format('Y-m-d'), $row['eligible'], $row['present'], $row['absent'] ?? 'Not configured', $row['rate'] ?? 'N/A', $row['cutoff']->setTimezone('Africa/Lagos')->toIso8601String()];
                }
            })();

            return CsvExport::download('summit-2026-attendance-summary.csv', ['Session', 'Date', 'Eligible registrants', 'Present', 'Absent', 'Rate (%)', 'Cutoff (WAT)'], $rows);
        }
        $rows = (function () use ($data, $reports) {
            foreach ((clone $data['list'])->with(['attendanceRecords' => fn ($query) => $query->whereNull('voided_at')])->orderBy('id')->lazyById(200) as $attendee) {
                $row = [$attendee->id, $attendee->first_name, $attendee->last_name, $attendee->phone_raw, $attendee->email, $attendee->attendance_preference];
                foreach ($data['sessions'] as $session) {
                    $present = $attendee->attendanceRecords->contains('conference_session_id', $session->id);
                    $registered = $attendee->registration_original_at_utc ?? $attendee->created_at;
                    $row[] = $present ? 'Present' : (! $session->ends_at_utc ? 'Not configured' : ($registered->lte($reports->cutoff($session)) ? 'Absent' : 'Not eligible'));
                }
                yield $row;
            }
        })();

        return CsvExport::download('summit-2026-attendance-matrix.csv', ['ID', 'First name', 'Surname', 'Phone', 'Email', 'Preference', ...$data['sessions']->pluck('label')->all()], $rows);
    }
}
