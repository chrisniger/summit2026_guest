<?php

namespace Tests\Feature;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\FollowUpActivity;
use App\Models\FollowUpStatusHistory;
use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Models\InfoRegistrationEvent;
use Carbon\Carbon;
use Database\Seeders\ConferenceSeeder;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_exactly_eight_unconfigured_sessions_are_seeded_idempotently(): void
    {
        $this->seed(ConferenceSeeder::class);
        $expected = [
            ['2026-10-27', 1, 'General', 'Day 1'], ['2026-10-28', 2, 'General', 'Day 2'],
            ['2026-10-29', 3, 'General', 'Day 3'], ['2026-10-30', 4, 'General', 'Day 4'],
            ['2026-10-31', 5, 'Morning', 'Day 5 Morning'], ['2026-10-31', 5, 'Evening', 'Day 5 Evening'],
            ['2026-11-01', 6, 'Morning', 'Day 6 Morning'], ['2026-11-01', 6, 'Evening', 'Day 6 Evening'],
        ];
        $sessions = ConferenceSession::orderBy('id')->get();
        $this->assertCount(8, $sessions);
        foreach ($sessions as $i => $session) {
            $this->assertSame($expected[$i], [$session->date_local->format('Y-m-d'), $session->day_number, $session->name, $session->label]);
            $this->assertNull($session->starts_at_utc);
            $this->assertNull($session->ends_at_utc);
            $this->assertFalse($session->enabled);
        }
        $this->assertSame('Africa/Lagos', ConferenceEvent::first()->timezone);
    }

    public function test_info_registration_and_first_time_membership_do_not_create_guests(): void
    {
        $attendee = InfoAttendee::factory()->create(['first_name' => 'Mike', 'member' => false, 'first_time' => true]);
        InfoRegistrationEvent::create(['info_attendee_id' => $attendee->id, 'source' => 'onsite', 'registered_at_utc' => now()]);
        $this->assertDatabaseCount('guest_records', 0);
        $this->assertDatabaseCount('follow_up_assignments', 0);
        $this->assertSame(1, $attendee->registrationEvents()->count());
    }

    public function test_same_phone_guests_and_attendees_remain_independent(): void
    {
        $attendee = InfoAttendee::factory()->create(['first_name' => 'Mike', 'phone_raw' => '08000000000']);
        $guest = GuestRecord::factory()->create(['full_name' => 'Mike', 'phone_raw' => $attendee->phone_raw]);
        $guest->update(['full_name' => 'Mike Guest']);
        $this->assertSame('Mike', $attendee->fresh()->first_name);
        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->assertSame('NOT CONTACTED', $guest->fresh()->current_status);
        $this->assertNull($guest->activeAssignment);
    }

    public function test_check_in_never_creates_or_updates_guest(): void
    {
        $attendee = InfoAttendee::factory()->create();
        $guest = GuestRecord::factory()->create();
        $before = $guest->fresh()->getAttributes();
        InfoAttendanceRecord::create(['info_attendee_id' => $attendee->id, 'conference_session_id' => ConferenceSession::first()->id, 'checked_in_at_utc' => now(), 'source' => 'manual']);
        $this->assertSame($before, $guest->fresh()->getAttributes());
        $this->assertDatabaseCount('guest_records', 1);
        $this->assertDatabaseCount('follow_up_assignments', 0);
    }

    public function test_guest_contact_history_does_not_change_attendance(): void
    {
        $actor = $this->userWithRole('super_admin');
        $guest = GuestRecord::factory()->create();
        $activity = FollowUpActivity::create(['guest_record_id' => $guest->id, 'actor_user_id' => $actor->id, 'method' => 'call', 'attempted_at_utc' => now(), 'outcome' => 'CONTACTED', 'previous_status' => 'NOT CONTACTED', 'new_status' => 'CONTACTED']);
        FollowUpStatusHistory::create(['guest_record_id' => $guest->id, 'from_status' => 'NOT CONTACTED', 'to_status' => 'CONTACTED', 'changed_by_user_id' => $actor->id, 'changed_at_utc' => now(), 'activity_id' => $activity->id]);
        $guest->forceFill(['current_status' => 'CONTACTED'])->save();
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->assertDatabaseCount('info_attendees', 0);
        $this->assertSame(1, $guest->activities()->count());
        $this->assertSame(1, $guest->statusHistory()->count());
    }

    public function test_guest_session_remains_selected_independently_of_entry_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 12:00:00', 'Africa/Lagos'));
        foreach (ConferenceSession::all() as $session) {
            $guest = GuestRecord::factory()->create(['conference_session_id' => $session->id]);
            $this->assertSame($session->label, $guest->session->label);
            $this->assertSame('2026-11-02', $guest->created_at->format('Y-m-d'));
        }
    }

    public function test_shared_phone_does_not_merge_people_within_either_domain(): void
    {
        InfoAttendee::factory()->count(2)->create(['phone_raw' => '08000000000']);
        GuestRecord::factory()->count(2)->create(['phone_raw' => '08000000000']);
        $this->assertDatabaseCount('info_attendees', 2);
        $this->assertDatabaseCount('guest_records', 2);
    }
}
