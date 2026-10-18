<?php

namespace Tests\Feature;

use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Services\CsvExport;
use App\Services\InfoReports;
use App\Services\SessionSchedule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class InfoReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00', 'Africa/Lagos'));
    }

    public function test_denominator_uses_original_registration_cutoff_and_excludes_guests(): void
    {
        $actor = $this->userWithRole('super_admin');
        $session = ConferenceSession::first();
        app(SessionSchedule::class)->configure($actor, $session, '09:00', '12:00', true);
        $one = InfoAttendee::factory()->create(['first_name' => 'PresentPerson', 'registration_original_at_utc' => '2026-10-27 07:00:00']);
        $absent = InfoAttendee::factory()->create(['first_name' => 'AbsentPerson', 'registration_original_at_utc' => '2026-10-27 07:00:00']);
        InfoAttendee::factory()->create(['first_name' => 'LatePerson', 'registration_original_at_utc' => '2026-10-28 07:00:00']);
        GuestRecord::factory()->count(4)->create();
        InfoAttendanceRecord::create(['info_attendee_id' => $one->id, 'conference_session_id' => $session->id, 'checked_in_at_utc' => '2026-10-27 08:00:00', 'source' => 'manual']);
        $data = app(InfoReports::class)->build(['session_id' => $session->id]);
        $this->assertSame(3, $data['registered']);
        $this->assertSame(2, $data['summary'][0]['eligible']);
        $this->assertSame(1, $data['summary'][0]['present']);
        $this->assertSame(1, $data['summary'][0]['absent']);
        $this->assertEquals(50, $data['summary'][0]['rate']);
        $this->actingAs($actor)->get('/info-com/reports?session_id='.$session->id.'&status=absent')->assertOk()->assertSee('AbsentPerson')->assertDontSee('LatePerson')->assertDontSee('PresentPerson');
    }

    public function test_day_rollups_and_check_ins_distinguish_people_from_sessions(): void
    {
        $attendee = InfoAttendee::factory()->create(['registration_original_at_utc' => '2026-10-27 07:00:00']);
        foreach (ConferenceSession::where('day_number', 5)->get() as $session) {
            InfoAttendanceRecord::create(['info_attendee_id' => $attendee->id, 'conference_session_id' => $session->id, 'checked_in_at_utc' => '2026-10-31 08:00:00', 'source' => 'manual']);
        }
        $data = app(InfoReports::class)->build(['day' => 5]);
        $this->assertSame(1, $data['unique_present']);
        $this->assertSame(2, $data['check_ins']);
        $this->assertSame(1, $data['days'][5]);
    }

    public function test_empty_denominator_and_unconfigured_schedule_show_na(): void
    {
        $data = app(InfoReports::class)->build([]);
        $this->assertSame(0, $data['registered']);
        $this->assertNull($data['summary'][0]['rate']);
        $this->assertNull($data['summary'][0]['absent']);
        $this->actingAs($this->userWithRole('info_com'))->get('/info-com/reports')->assertOk()->assertSee('N/A');
    }

    public function test_export_applies_same_cohort_filter_and_escapes_formula_cells(): void
    {
        $actor = $this->userWithRole('super_admin');
        InfoAttendee::factory()->create(['first_name' => '=HYPERLINK(1)', 'last_name' => 'Export', 'attendance_preference' => 'Virtual']);
        InfoAttendee::factory()->create(['first_name' => 'Excluded', 'attendance_preference' => 'In Person']);
        $response = $this->actingAs($actor)->get('/info-com/reports/export?attendance_preference=Virtual');
        $response->assertOk()->assertDownload();
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(1)", $content);
        $this->assertStringNotContainsString('Excluded', $content);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
        $this->get('/info-com/reports/export?format=summary')->assertDownload();
        foreach (['=formula', '+2348012345678', '-1', '@SUM(1)', " \t=SUM(1)"] as $value) {
            $this->assertSame("'".$value, CsvExport::cell($value));
        }
    }

    public function test_info_team_cannot_export_pii_or_access_imports_without_grant(): void
    {
        $this->actingAs($this->userWithRole('info_com'))->get('/info-com/reports/export')->assertForbidden();
        $this->get('/info-com/imports')->assertForbidden();
        $this->get('/info-com/reports?session_id=999999')->assertSessionHasErrors('session_id');
        $this->get('/info-com/reports?status=absent')->assertSessionHasErrors('session_id');
    }

    public function test_voided_presence_is_excluded_from_counts_and_matrix(): void
    {
        $attendee = InfoAttendee::factory()->create(['registration_original_at_utc' => '2026-10-27 07:00:00']);
        $actor = $this->userWithRole('super_admin');
        $session = ConferenceSession::first();
        app(SessionSchedule::class)->configure($actor, $session, '09:00', '12:00', true);
        InfoAttendanceRecord::create(['info_attendee_id' => $attendee->id, 'conference_session_id' => $session->id, 'checked_in_at_utc' => '2026-10-27 08:00:00', 'source' => 'manual', 'voided_at' => now(), 'void_reason' => 'Wrong record']);
        $data = app(InfoReports::class)->build(['session_id' => $session->id]);
        $this->assertSame(0, $data['check_ins']);
        $this->assertSame(1, $data['summary'][0]['absent']);
        $response = $this->actingAs($actor)->get('/info-com/reports/export?session_id='.$session->id)->assertOk();
        $this->assertStringContainsString('Absent', $response->streamedContent());
    }
}
