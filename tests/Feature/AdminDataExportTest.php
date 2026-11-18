<?php

namespace Tests\Feature;

use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use App\Models\InfoAttendee;
use App\Services\SessionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDataExportTest extends TestCase
{
    public function test_only_super_admin_can_download_complete_data(): void
    {
        $types = ['guests', 'registrations', 'registration-events', 'attendance'];
        foreach (['follow_up_admin', 'info_com', 'data_entry', 'officer'] as $role) {
            $this->actingAs($this->userWithRole($role));
            foreach ($types as $type) {
                $this->get(route('admin.exports.download', $type))->assertForbidden();
            }
        }

        $this->actingAs($this->userWithRole('super_admin'))->get(route('admin'))
            ->assertOk()->assertSee('Full guest data')->assertSee('Full registration data')
            ->assertSee('Registration entry history')->assertSee('Full attendance data');
        $this->get(route('admin.exports.download', 'unknown'))->assertNotFound();
    }

    public function test_complete_guest_download_includes_archived_and_deleted_records(): void
    {
        $admin = $this->userWithRole('super_admin');
        GuestRecord::factory()->create(['full_name' => '=Visible Guest', 'home_address' => '12 Main Street']);
        $archived = GuestRecord::factory()->create(['full_name' => 'Archived Guest']);
        $archived->forceFill(['archived_at' => now()])->save();
        $deleted = GuestRecord::factory()->create(['full_name' => 'Deleted Guest']);
        $deleted->delete();

        $this->actingAs($admin)->get(route('guests.index'))->assertSee('Download full guest data');
        $response = $this->get(route('admin.exports.download', ['type' => 'guests', 'search' => 'ignored']))
            ->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Home address', $csv);
        $this->assertStringContainsString("'=Visible Guest", $csv);
        $this->assertStringContainsString('12 Main Street', $csv);
        $this->assertStringContainsString('Archived Guest', $csv);
        $this->assertStringContainsString('Deleted Guest', $csv);
        $this->assertDatabaseHas('audit_logs', ['domain' => 'platform', 'action' => 'data.exported', 'actor_user_id' => $admin->id]);
    }

    public function test_attendance_page_entry_appears_in_registration_event_and_attendance_downloads(): void
    {
        $admin = $this->userWithRole('super_admin');
        $session = ConferenceSession::firstOrFail();
        app(SessionSchedule::class)->configure($admin, $session, '09:00', '12:00', true);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));
        $this->post(route('attendance.public.store'), [
            'first_name' => 'Public', 'last_name' => 'Checkin', 'phone_raw' => '08011112222',
            'submission_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('attendance.public'));

        $attendee = InfoAttendee::sole();
        $this->assertSame('other', $attendee->registration_source);
        $this->actingAs($admin)->get(route('info.reports'))->assertSee('Download full registration data');
        $registrations = $this->get(route('admin.exports.download', 'registrations'))->assertOk()->assertDownload()->streamedContent();
        $events = $this->get(route('admin.exports.download', 'registration-events'))->assertOk()->assertDownload()->streamedContent();
        $attendance = $this->get(route('admin.exports.download', 'attendance'))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString('Public,Checkin,08011112222', $registrations);
        $this->assertStringContainsString(',other,', $registrations);
        $this->assertStringContainsString('Public,Checkin,08011112222', $events);
        $this->assertStringContainsString('Public,Checkin,08011112222', $attendance);
        $this->assertStringContainsString('registration_auto', $attendance);
        $this->assertStringContainsString($session->label, $attendance);
    }

    public function test_non_admin_pages_do_not_show_full_download_links(): void
    {
        $this->get(route('attendance.public'))->assertOk()->assertDontSee('Full registration data');
        $this->actingAs($this->userWithRole('follow_up_admin'))->get(route('guests.index'))
            ->assertOk()->assertDontSee('Download full guest data');
        $this->actingAs($this->userWithRole('info_com'))->get(route('info.reports'))
            ->assertOk()->assertDontSee('Download full registration data');
    }
}
