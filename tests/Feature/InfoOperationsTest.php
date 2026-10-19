<?php

namespace Tests\Feature;

use App\Livewire\CheckInDesk;
use App\Models\AuditLog;
use App\Models\ConferenceSession;
use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Services\SessionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class InfoOperationsTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge(['first_name' => 'Mike', 'last_name' => 'Test', 'phone_raw' => '08012345678', 'submission_id' => (string) Str::uuid()], $overrides);
    }

    private function configure(ConferenceSession $session, string $start = '09:00', string $end = '12:00'): void
    {
        app(SessionSchedule::class)->configure($this->userWithRole('super_admin'), $session, $start, $end, true);
    }

    public function test_onsite_registration_is_independent_and_idempotent(): void
    {
        $actor = $this->userWithRole('info_com');
        $this->actingAs($actor);
        $data = $this->payload(['member' => 0, 'first_time' => 1]);
        $this->post('/info-com/attendees', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/info-com/attendees', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertDatabaseCount('info_registration_events', 1);
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->assertDatabaseCount('guest_records', 0);
        $this->assertDatabaseHas('info_attendees', ['phone_normalized' => '+2348012345678', 'member' => false, 'first_time' => true]);
    }

    public function test_all_eight_sessions_auto_check_in_only_the_correct_session(): void
    {
        $actor = $this->userWithRole('info_com');
        foreach (ConferenceSession::all() as $session) {
            $start = $session->name === 'Evening' ? '17:00' : '09:00';
            $end = $session->name === 'Evening' ? '19:00' : '12:00';
            $this->configure($session, $start, $end);
            $this->travelTo(CarbonImmutable::parse($session->date_local->format('Y-m-d').' '.$start, 'Africa/Lagos'));
            $this->actingAs($actor)->post('/info-com/attendees', $this->payload())->assertSessionHasNoErrors();
            $attendee = InfoAttendee::latest('id')->first();
            $this->assertSame(1, $attendee->attendanceRecords()->count());
            $this->assertSame($session->id, $attendee->attendanceRecords()->first()->conference_session_id);
        }
        $this->assertDatabaseCount('info_attendance_records', 8);
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_virtual_registration_and_exclusive_end_create_no_presence(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $actor = $this->userWithRole('info_com');
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));
        $this->actingAs($actor)->post('/info-com/attendees', $this->payload(['attendance_preference' => 'Virtual']))->assertSessionHasNoErrors();
        $this->travelTo(CarbonImmutable::parse('2026-10-27 12:00', 'Africa/Lagos'));
        $this->post('/info-com/attendees', $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('info_attendance_records', 0);
    }

    public function test_manual_check_in_is_idempotent_and_revalidates_displayed_session(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));
        $attendee = InfoAttendee::factory()->create();
        $this->actingAs($this->userWithRole('info_com'));
        $url = '/info-com/attendees/'.$attendee->id.'/check-in';
        $this->post($url, ['conference_session_id' => $session->id])->assertSessionHas('status', 'Present — check-in saved.');
        $this->post($url, ['conference_session_id' => $session->id])->assertSessionHas('status', 'Already present — no duplicate check-in.');
        $this->assertDatabaseCount('info_attendance_records', 1);
        $this->post($url, ['conference_session_id' => $session->id + 1])->assertStatus(409);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 12:00', 'Africa/Lagos'));
        $this->post($url, ['conference_session_id' => $session->id])->assertStatus(409);
    }

    public function test_locked_session_rejects_check_in_without_changing_guests(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $session->update(['attendance_locked_at' => now()]);
        $guest = GuestRecord::factory()->create();
        $before = $guest->fresh()->getAttributes();
        $attendee = InfoAttendee::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));
        $this->actingAs($this->userWithRole('info_com'))->post('/info-com/attendees/'.$attendee->id.'/check-in', ['conference_session_id' => $session->id])->assertStatus(409);
        $this->assertSame($before, $guest->fresh()->getAttributes());
        $this->assertDatabaseCount('info_attendance_records', 0);
    }

    public function test_phone_variants_and_shared_phone_search_keep_people_separate(): void
    {
        $actor = $this->userWithRole('info_com');
        $this->actingAs($actor);
        $this->post('/info-com/attendees', $this->payload(['first_name' => 'Mike']));
        $this->post('/info-com/attendees', $this->payload(['first_name' => 'Jane', 'phone_raw' => '+2348012345678']));
        foreach (['0801234', '234801234', '+234801234'] as $search) {
            Livewire::actingAs($actor)->test(CheckInDesk::class)->set('search', $search)->assertSee('Mike')->assertSee('Jane');
        }
        Livewire::test(CheckInDesk::class)->set('search', 'Mike Test')->assertSee('Mike')->assertDontSee('Jane');
        $this->assertDatabaseCount('info_attendees', 2);
    }

    public function test_check_in_desk_marks_present_in_one_click_and_resets_search(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));
        $actor = $this->userWithRole('info_com');
        $attendee = InfoAttendee::factory()->create();
        Livewire::actingAs($actor)->test(CheckInDesk::class)
            ->set('search', $attendee->first_name)
            ->call('mark', $attendee->id, $session->id)
            ->assertHasNoErrors()->assertSet('search', '')->assertDispatched('check-in-saved')->assertSee('Checked in')->assertDontSee('Attendee #')->assertSeeHtml('is-present');
        $this->assertDatabaseHas('info_attendance_records', ['info_attendee_id' => $attendee->id, 'conference_session_id' => $session->id]);
    }

    public function test_validation_rejects_invalid_phone_and_unauthorized_roles(): void
    {
        $attendee = InfoAttendee::factory()->create();
        foreach (['follow_up_admin', 'data_entry', 'officer'] as $role) {
            $this->actingAs($this->userWithRole($role));
            foreach (['/info-com/check-in', '/info-com/attendees/create', '/info-com/attendees/'.$attendee->id, '/info-com/reports', '/info-com/imports'] as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post('/info-com/attendees', $this->payload())->assertForbidden();
            Livewire::test(CheckInDesk::class)->assertForbidden();
        }
        $this->actingAs($this->userWithRole('info_com'))->post('/info-com/attendees', $this->payload(['phone_raw' => 'not a phone']))->assertSessionHasErrors('phone_raw');
    }

    public function test_super_admin_can_void_and_restore_with_reason_without_extra_rows(): void
    {
        $actor = $this->userWithRole('super_admin');
        $attendee = InfoAttendee::factory()->create();
        $record = InfoAttendanceRecord::create(['info_attendee_id' => $attendee->id, 'conference_session_id' => ConferenceSession::first()->id, 'checked_in_at_utc' => now(), 'source' => 'manual']);
        $url = '/info-com/attendance/'.$record->id.'/correct';
        $this->actingAs($this->userWithRole('info_com'))->post($url, ['action' => 'void', 'reason' => 'Wrong attendee'])->assertForbidden();
        $this->actingAs($actor)->post($url, ['action' => 'void'])->assertSessionHasErrors('reason');
        $this->post($url, ['action' => 'void', 'reason' => 'Wrong attendee selected'])->assertSessionHasNoErrors();
        $this->assertNotNull($record->fresh()->voided_at);
        $this->post($url, ['action' => 'restore', 'reason' => 'Identity confirmed by desk'])->assertSessionHasNoErrors();
        $this->assertNull($record->fresh()->voided_at);
        $this->assertDatabaseCount('info_attendance_records', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.restore', 'subject_id' => $record->id]);
    }

    public function test_attendee_edit_preserves_history_and_cannot_set_provenance(): void
    {
        $actor = $this->userWithRole('info_com');
        $attendee = InfoAttendee::factory()->create(['first_name' => 'Original']);
        $this->actingAs($actor)->patch('/info-com/attendees/'.$attendee->id, $this->payload(['first_name' => 'Corrected', 'registration_source' => 'csv']))->assertSessionHasNoErrors();
        $this->assertSame('Corrected', $attendee->fresh()->first_name);
        $audit = AuditLog::where('action', 'attendee.updated')->latest('id')->firstOrFail();
        $this->assertContains('first_name', $audit->after_json['changed_fields']);
        $this->assertStringNotContainsString('Corrected', $audit->toJson());
        $this->assertSame('onsite', $attendee->fresh()->registration_source);
        $this->assertDatabaseCount('info_registration_events', 0);
        $this->assertDatabaseCount('guest_records', 0);
    }

    public function test_all_new_info_pages_render_for_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $attendee = InfoAttendee::factory()->create();
        foreach (['/info-com/check-in', '/info-com/attendees/create', '/info-com/attendees/'.$attendee->id, '/info-com/attendees/'.$attendee->id.'/edit', '/info-com/reports', '/info-com/imports'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
