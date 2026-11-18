<?php

namespace App\Http\Controllers;

use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Models\InfoRegistrationEvent;
use App\Services\Audit;
use App\Services\CsvExport;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDataExportController extends Controller
{
    public function download(Request $request, string $type, Audit $audit): StreamedResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        abort_unless(in_array($type, ['guests', 'registrations', 'registration-events', 'attendance'], true), 404);

        $audit->record('platform', 'data.exported', $request->user(), $request->user(), [], ['source' => $type]);
        $stamp = now()->setTimezone(config('summit.timezone'))->format('Ymd-His');

        return match ($type) {
            'guests' => CsvExport::download('summit-all-guests-'.$stamp.'.csv',
                ['ID', 'Full name', 'First name', 'Surname', 'Phone', 'Normalized phone', 'Gender', 'Home address',
                    'Heard about', 'Event year', 'Encounter date', 'Session', 'Status', 'Current officer ID',
                    'Current officer', 'Assigned at (WAT)', 'Entry notes', 'Original capture (WAT)',
                    'Entered by user ID', 'Import row ID', 'Original source data', 'Archived at (WAT)',
                    'Deleted at (WAT)', 'Created at (WAT)', 'Updated at (WAT)'],
                $this->guests()),
            'registrations' => CsvExport::download('summit-all-registrations-'.$stamp.'.csv',
                ['ID', 'First name', 'Surname', 'Phone', 'Normalized phone', 'Email', 'Church member',
                    'Summit centre', 'Commits to invite', 'Invite count', 'Location', 'Abuja area',
                    'First conference attendance', 'Bus shuttle', 'Bus route', 'Attendance preference',
                    'Heard about', 'Feedback', 'Consent', 'Original registration (WAT)',
                    'Registration source', 'External source ID', 'Entered by user ID',
                    'Original source data', 'Deleted at (WAT)', 'Created at (WAT)', 'Updated at (WAT)'],
                $this->registrations()),
            'registration-events' => CsvExport::download('summit-all-registration-events-'.$stamp.'.csv',
                ['Event ID', 'Attendee ID', 'First name', 'Surname', 'Phone', 'Registration source',
                    'Attendee source', 'Registered at (WAT)', 'Import row ID', 'Entered by user ID',
                    'Created at (WAT)'],
                $this->registrationEvents()),
            'attendance' => CsvExport::download('summit-all-attendance-'.$stamp.'.csv',
                ['Attendance ID', 'Attendee ID', 'First name', 'Surname', 'Phone', 'Event year',
                    'Conference date', 'Session', 'Checked in at (WAT)', 'Attendance source',
                    'Marked by user ID', 'Registration event ID', 'Import row ID',
                    'Voided at (WAT)', 'Void reason', 'Created at (WAT)'],
                $this->attendance()),
        };
    }

    private function guests(): \Generator
    {
        foreach (GuestRecord::withTrashed()->with(['session.event', 'activeAssignment.officer'])->orderBy('id')->lazyById(200) as $guest) {
            yield [$guest->id, $guest->full_name, $guest->first_name, $guest->last_name, $guest->phone_raw,
                $guest->phone_normalized, $guest->gender, $guest->home_address, $guest->heard_about,
                $guest->session->event->year, $guest->session->date_local->format('Y-m-d'), $guest->session->label,
                $guest->current_status, $guest->activeAssignment?->officer_user_id,
                $guest->activeAssignment?->officer?->name, $this->wat($guest->activeAssignment?->assigned_at),
                $guest->entry_notes, $this->wat($guest->captured_at_original_utc), $guest->created_by_user_id,
                $guest->import_row_id, $this->json($guest->raw_source_json), $this->wat($guest->archived_at),
                $this->wat($guest->deleted_at), $this->wat($guest->created_at), $this->wat($guest->updated_at)];
        }
    }

    private function registrations(): \Generator
    {
        foreach (InfoAttendee::withTrashed()->orderBy('id')->lazyById(200) as $attendee) {
            yield [$attendee->id, $attendee->first_name, $attendee->last_name, $attendee->phone_raw,
                $attendee->phone_normalized, $attendee->email, $this->yesNo($attendee->member),
                $attendee->summit_centre, $this->yesNo($attendee->commits_to_invite), $attendee->invite_count,
                $attendee->location, $attendee->abuja_area, $this->yesNo($attendee->first_time),
                $this->yesNo($attendee->bus_shuttle), $attendee->bus_route, $attendee->attendance_preference,
                $attendee->heard_about, $attendee->feedback, $this->yesNo($attendee->consent),
                $this->wat($attendee->registration_original_at_utc), $attendee->registration_source,
                $attendee->source_external_id, $attendee->created_by_user_id,
                $this->json($attendee->raw_source_json), $this->wat($attendee->deleted_at),
                $this->wat($attendee->created_at), $this->wat($attendee->updated_at)];
        }
    }

    private function registrationEvents(): \Generator
    {
        foreach (InfoRegistrationEvent::with(['attendee' => fn ($query) => $query->withTrashed()])->orderBy('id')->lazyById(200) as $event) {
            yield [$event->id, $event->info_attendee_id, $event->attendee?->first_name,
                $event->attendee?->last_name, $event->attendee?->phone_raw, $event->source,
                $event->attendee?->registration_source, $this->wat($event->registered_at_utc),
                $event->import_row_id, $event->created_by_user_id, $this->wat($event->created_at)];
        }
    }

    private function attendance(): \Generator
    {
        foreach (InfoAttendanceRecord::with(['attendee' => fn ($query) => $query->withTrashed(), 'session.event'])->orderBy('id')->lazyById(200) as $record) {
            yield [$record->id, $record->info_attendee_id, $record->attendee?->first_name,
                $record->attendee?->last_name, $record->attendee?->phone_raw,
                $record->session->event->year, $record->session->date_local->format('Y-m-d'),
                $record->session->label, $this->wat($record->checked_in_at_utc), $record->source,
                $record->marked_by_user_id, $record->registration_event_id, $record->import_row_id,
                $this->wat($record->voided_at), $record->void_reason, $this->wat($record->created_at)];
        }
    }

    private function wat(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone(config('summit.timezone'))->format('Y-m-d H:i:s');
    }

    private function yesNo(mixed $value): string
    {
        return $value === null ? '' : ($value ? 'Yes' : 'No');
    }

    private function json(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
