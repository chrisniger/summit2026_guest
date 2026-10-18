<?php

namespace Tests\Feature;

use App\Models\ConferenceSession;
use App\Models\FollowUpAssignment;
use App\Models\GuestRecord;
use App\Models\InfoAttendanceRecord;
use App\Models\InfoAttendee;
use App\Services\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    public function test_attendee_can_attend_eight_sessions_once_each(): void
    {
        $attendee = InfoAttendee::factory()->create();
        foreach (ConferenceSession::all() as $session) {
            InfoAttendanceRecord::create(['info_attendee_id' => $attendee->id, 'conference_session_id' => $session->id, 'checked_in_at_utc' => now(), 'source' => 'manual']);
        }
        $this->assertSame(8, $attendee->attendanceRecords()->count());
        $this->assertSame(1, InfoAttendanceRecord::distinct()->count('info_attendee_id'));
        $this->expectException(QueryException::class);
        InfoAttendanceRecord::create(['info_attendee_id' => $attendee->id, 'conference_session_id' => ConferenceSession::first()->id, 'checked_in_at_utc' => now(), 'source' => 'manual']);
    }

    public function test_guest_rejects_missing_or_nonexistent_session(): void
    {
        $this->expectException(QueryException::class);
        GuestRecord::factory()->create(['conference_session_id' => 999999]);
    }

    public function test_guest_requires_session_and_has_no_cross_domain_key(): void
    {
        $columns = Schema::getColumnListing('guest_records');
        $this->assertNotContains('info_attendee_id', $columns);
        $this->assertNotContains('guest_record_id', Schema::getColumnListing('info_attendees'));
        $this->expectException(QueryException::class);
        DB::table('guest_records')->insert(['full_name' => 'Missing Session', 'phone_raw' => '08000000000']);
    }

    public function test_only_one_active_assignment_per_guest_is_allowed(): void
    {
        $actor = $this->userWithRole('super_admin');
        $officer = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $data = ['guest_record_id' => $guest->id, 'officer_user_id' => $officer->id, 'assigned_by_user_id' => $actor->id, 'assigned_at' => now()];
        FollowUpAssignment::create($data);
        $this->expectException(QueryException::class);
        FollowUpAssignment::create($data);
    }

    public function test_invalid_follow_up_status_is_rejected_by_database(): void
    {
        $guest = GuestRecord::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('guest_records')->where('id', $guest->id)->update(['current_status' => 'UNKNOWN']);
    }

    public function test_database_rejects_enabled_session_without_boundaries(): void
    {
        $this->expectException(QueryException::class);
        DB::table('conference_sessions')->where('id', ConferenceSession::first()->id)->update(['enabled' => true]);
    }

    public function test_audit_service_redacts_sensitive_fields_and_prevents_edits(): void
    {
        $actor = $this->userWithRole('super_admin');
        $audit = app(Audit::class)->record('platform', 'test', $actor, $actor, [], ['password' => 'secret', 'phone_raw' => 'private', 'is_active' => true]);
        $this->assertSame(['is_active' => true], $audit->after_json);
        $this->expectException(\LogicException::class);
        $audit->update(['action' => 'tampered']);
    }

    public function test_guest_default_status_is_database_enforced(): void
    {
        $id = DB::table('guest_records')->insertGetId(['full_name' => 'Test', 'phone_raw' => '08000000000', 'conference_session_id' => ConferenceSession::first()->id]);
        $this->assertDatabaseHas('guest_records', ['id' => $id, 'current_status' => 'NOT CONTACTED']);
        $this->assertDatabaseCount('follow_up_assignments', 0);
    }
}
