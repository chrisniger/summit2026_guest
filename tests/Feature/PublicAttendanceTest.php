<?php

namespace Tests\Feature;

use App\Livewire\CheckInDesk;
use App\Models\ConferenceSession;
use App\Models\InfoAttendee;
use App\Services\SessionSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PublicAttendanceTest extends TestCase
{
    private function configure(ConferenceSession $session, string $start = '09:00', string $end = '12:00'): void
    {
        app(SessionSchedule::class)->configure($this->userWithRole('super_admin'), $session, $start, $end, true);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Chidi',
            'last_name' => 'Okeke',
            'phone_raw' => '08012345678',
            'submission_id' => (string) Str::uuid(),
        ], $overrides);
    }

    public function test_public_attendance_page_is_open_without_login(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));

        $this->get('/info-com/attendance')
            ->assertOk()
            ->assertSee('Enter your details to mark the active session present.')
            ->assertSee('First name')
            ->assertSee('Last name')
            ->assertSee('Phone number')
            ->assertSee($session->label)
            ->assertDontSee('Sign in');
        $this->assertGuest();
    }

    public function test_last_six_phone_digits_mark_an_existing_attendee_present(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));
        $attendee = InfoAttendee::factory()->create([
            'first_name' => 'Stored', 'last_name' => 'Name',
            'phone_raw' => '08012345678', 'phone_normalized' => '+2348012345678',
        ]);

        $this->post('/info-com/attendance', $this->payload(['phone_raw' => '09012345678']))
            ->assertRedirect('/info-com/attendance')
            ->assertSessionHas('status', 'Welcome to SUMMIT 2026, Chidi Okeke. You are marked present.');

        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertDatabaseCount('info_registration_events', 0);
        $this->assertDatabaseHas('info_attendance_records', [
            'info_attendee_id' => $attendee->id,
            'conference_session_id' => $session->id,
            'marked_by_user_id' => null,
        ]);
    }

    public function test_unknown_phone_creates_the_registration_and_marks_it_present(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));

        $this->post('/info-com/attendance', $this->payload(['phone_raw' => '0809 555 0111']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Welcome to SUMMIT 2026, Chidi Okeke. You are marked present.');

        $attendee = InfoAttendee::sole();
        $this->assertSame('Chidi', $attendee->first_name);
        $this->assertSame('other', $attendee->registration_source);
        $this->assertSame('+2348095550111', $attendee->phone_normalized);
        $this->get('/info-com/attendance')->assertOk()
            ->assertSee('You are marked present.')
            ->assertSee('Mark Additional Attendance')
            ->assertSeeHtml('<details class="additional-details">')
            ->assertDontSeeHtml('<details class="additional-details" open>')
            ->assertSeeHtml('name="first_name" value=""');
        $this->assertDatabaseHas('info_registration_events', ['info_attendee_id' => $attendee->id, 'source' => 'onsite']);
        $this->assertDatabaseHas('info_attendance_records', [
            'info_attendee_id' => $attendee->id,
            'conference_session_id' => $session->id,
        ]);
    }

    public function test_public_attendance_is_closed_when_no_session_is_active(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));

        $this->get('/info-com/attendance')->assertOk()->assertSee('Check-in is not open right now.');
        $this->post('/info-com/attendance', $this->payload())
            ->assertSessionHas('attendance_error', 'Check-in is not open right now. Please see the Info Com desk.');

        $this->assertDatabaseCount('info_attendees', 0);
        $this->assertDatabaseCount('info_attendance_records', 0);
    }

    public function test_public_attendance_rejects_invalid_details(): void
    {
        $session = ConferenceSession::first();
        $this->configure($session);
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:00', 'Africa/Lagos'));

        $this->post('/info-com/attendance', $this->payload(['first_name' => '', 'phone_raw' => 'not a phone']))
            ->assertSessionHasErrors(['first_name', 'phone_raw']);
        $this->assertDatabaseCount('info_attendees', 0);
    }

    public function test_check_in_heading_is_compact(): void
    {
        $this->actingAs($this->userWithRole('info_com'))->get('/info-com/check-in')
            ->assertOk()
            ->assertSee('Mark the active session present.')
            ->assertSee('class="button primary">Register', false)
            ->assertDontSee('INFO COM · ATTENDANCE')
            ->assertDontSee('Find the right attendee');
    }

    public function test_phone_search_lists_matching_numbers_for_info_com(): void
    {
        $actor = $this->userWithRole('info_com');
        $attendee = InfoAttendee::factory()->create(['phone_raw' => '08012345678', 'phone_normalized' => '+2348012345678']);

        Livewire::actingAs($actor)->test(CheckInDesk::class)
            ->set('search', '0801234')
            ->assertSee('Matching numbers')
            ->assertSeeHtml('class="phone-number"')
            ->call('useNumber', $attendee->id)
            ->assertSet('search', '08012345678');
    }
}
