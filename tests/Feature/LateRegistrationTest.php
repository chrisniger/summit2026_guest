<?php

namespace Tests\Feature;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Models\InfoRegistrationEvent;
use App\Services\InfoReports;
use App\Services\SessionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class LateRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-28 10:00', 'Africa/Lagos'));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['first_name' => 'Paper', 'last_name' => 'Registration', 'phone_raw' => '08012345678',
            'submission_id' => (string) Str::uuid(), 'other_session' => 1,
            'attendance_session_id' => ConferenceSession::where('code', '2026-10-27-G')->firstOrFail()->id], $overrides);
    }

    public function test_late_registration_marks_only_the_chosen_disabled_session_and_counts_in_reports(): void
    {
        $admin = $this->userWithRole('super_admin');
        $dayOne = ConferenceSession::where('code', '2026-10-27-G')->firstOrFail();
        $dayTwo = ConferenceSession::where('code', '2026-10-28-G')->firstOrFail();
        $schedule = app(SessionSchedule::class);
        $schedule->configure($admin, $dayOne, '09:00', '12:00', false);
        $schedule->configure($admin, $dayTwo, '09:00', '12:00', true);
        $actor = $this->userWithRole('info_com');
        $this->actingAs($actor)->post('/info-com/attendees', $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $attendee = InfoAttendee::sole();
        $record = InfoAttendanceRecord::sole();
        $this->assertSame($dayOne->id, $record->conference_session_id);
        $this->assertSame('manual', $record->source);
        $this->assertSame($actor->id, $record->marked_by_user_id);
        $this->assertTrue(InfoRegistrationEvent::sole()->registered_at_utc->equalTo(now()));
        $this->assertTrue($record->checked_in_at_utc->equalTo(now()));
        $this->assertTrue($attendee->registration_original_at_utc->equalTo(now()));
        $report = app(InfoReports::class)->build(['session_id' => $dayOne->id, 'status' => 'present']);
        $this->assertSame(1, $report['summary'][0]['eligible']);
        $this->assertSame(1, $report['summary'][0]['present']);
        $this->assertSame(0, $report['summary'][0]['absent']);
        $this->assertSame([$attendee->id], $report['list']->pluck('id')->all());
        $this->actingAs($admin)->get('/info-com/reports/export?session_id='.$dayOne->id.'&status=present')->assertOk()->assertDownload();
    }

    public function test_unchecked_other_session_ignores_the_dropdown_and_uses_the_active_session(): void
    {
        $dayTwo = ConferenceSession::where('code', '2026-10-28-G')->firstOrFail();
        app(SessionSchedule::class)->configure($this->userWithRole('super_admin'), $dayTwo, '09:00', '12:00', true);
        $this->actingAs($this->userWithRole('info_com'))->post('/info-com/attendees', $this->payload(['other_session' => 0]))->assertSessionHasNoErrors();
        $this->assertSame($dayTwo->id, InfoAttendanceRecord::sole()->conference_session_id);
    }

    public function test_selection_is_required_and_future_locked_and_other_year_sessions_are_rejected_atomically(): void
    {
        $locked = ConferenceSession::where('code', '2026-10-27-G')->firstOrFail();
        $locked->update(['attendance_locked_at' => now()]);
        $future = ConferenceSession::where('code', '2026-10-29-G')->firstOrFail();
        $event = ConferenceEvent::create(['year' => 2025, 'name' => 'SUMMIT 2025', 'timezone' => 'Africa/Lagos', 'starts_on' => '2025-10-27', 'ends_on' => '2025-11-01']);
        $historical = ConferenceSession::create(['conference_event_id' => $event->id, 'code' => 'HISTORICAL-TEST',
            'day_number' => 1, 'date_local' => '2025-10-27', 'name' => 'General', 'label' => 'Historical']);
        $this->actingAs($this->userWithRole('info_com'));
        foreach ([null, 999999, $locked->id, $future->id, $historical->id] as $id) {
            $this->post('/info-com/attendees', $this->payload(['attendance_session_id' => $id]))->assertSessionHasErrors('attendance_session_id');
        }
        $this->assertDatabaseCount('info_attendees', 0);
        $this->assertDatabaseCount('info_registration_events', 0);
        $this->assertDatabaseCount('info_attendance_records', 0);
    }

    public function test_a_session_starting_later_today_cannot_be_selected(): void
    {
        $session = ConferenceSession::where('code', '2026-10-28-G')->firstOrFail();
        app(SessionSchedule::class)->configure($this->userWithRole('super_admin'), $session, '17:00', '19:00', true);
        $this->actingAs($this->userWithRole('info_com'))->post('/info-com/attendees', $this->payload(['attendance_session_id' => $session->id]))->assertSessionHasErrors('attendance_session_id');
        $this->assertDatabaseCount('info_attendees', 0);
    }

    public function test_replayed_and_matching_registrations_do_not_duplicate_selected_session_attendance(): void
    {
        $this->actingAs($this->userWithRole('info_com'));
        $payload = $this->payload();
        $this->post('/info-com/attendees', $payload)->assertSessionHasNoErrors();
        $this->post('/info-com/attendees', $payload)->assertSessionHasNoErrors();
        $this->post('/info-com/attendees', $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertDatabaseCount('info_registration_events', 2);
        $this->assertDatabaseCount('info_attendance_records', 1);
    }

    public function test_registration_lists_only_available_sessions_and_editing_does_not_change_attendance(): void
    {
        $locked = ConferenceSession::where('code', '2026-10-27-G')->firstOrFail();
        $locked->update(['attendance_locked_at' => now()]);
        $this->actingAs($this->userWithRole('info_com'))->get('/info-com/attendees/create')->assertOk()->assertSee('Other Session')
            ->assertViewHas('registrationSessions', fn ($sessions) => $sessions->count() === 1 && $sessions->first()->code === '2026-10-28-G');
        $attendee = InfoAttendee::factory()->create();
        $this->get('/info-com/attendees/'.$attendee->id.'/edit')->assertOk()->assertDontSee('Other Session');
        $this->patch('/info-com/attendees/'.$attendee->id, $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('info_attendance_records', 0);
    }

    public function test_other_roles_cannot_use_late_registration(): void
    {
        foreach (['follow_up_admin', 'data_entry', 'officer'] as $role) {
            $this->actingAs($this->userWithRole($role))->post('/info-com/attendees', $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('info_attendees', 0);
    }
}
