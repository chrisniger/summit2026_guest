<?php

namespace Tests\Feature;

use App\Models\ConferenceEvent;
use App\Models\ConferenceSession;
use App\Models\GuestImportBatch;
use App\Models\GuestImportRow;
use App\Models\GuestRecord;
use App\Models\User;
use App\Services\FollowUpAssignments;
use App\Services\FollowUpContact;
use App\Services\FollowUpReports;
use Illuminate\Support\Str;
use Tests\TestCase;

class FollowUpReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(12, 0));
    }

    private function assign(User $admin, GuestRecord $guest, User $officer): void
    {
        $service = app(FollowUpAssignments::class);
        $service->commit($admin, $service->preview($admin, ['guest_ids' => [$guest->id], 'action' => 'reassign',
            'officer_user_id' => $officer->id, 'reason' => 'Reviewed session allocation'])['token']);
    }

    private function contact(User $actor, GuestRecord $guest, string $outcome, string $time, string $comment = 'Reviewed contact attempt'): void
    {
        app(FollowUpContact::class)->record($actor, $guest, ['submission_id' => (string) Str::uuid(),
            'assignment_id' => $guest->activeAssignment()->first()?->id, 'version' => $guest->fresh()->version,
            'method' => 'call', 'outcome' => $outcome, 'attempted_at' => $time, 'comment' => $comment,
            'two_way_confirmed' => 1, 'failed_attempt_confirmed' => 1]);
    }

    private function cohort(): array
    {
        $admin = $this->userWithRole('follow_up_admin');
        $one = $this->userWithRole('officer', ['name' => 'Officer One']);
        $two = $this->userWithRole('officer', ['name' => 'Officer Two']);
        $guests = GuestRecord::factory()->count(3)->create();
        $this->assign($admin, $guests[0], $one);
        $this->assign($admin, $guests[1], $two);
        $this->contact($one, $guests[0], 'UNREACHABLE', '2026-11-02T09:00');
        $this->contact($one, $guests[0], 'CONTACTED', '2026-11-02T10:00');
        $this->contact($one, $guests[0], 'NOTE_ONLY', '2026-11-02T11:00');
        $this->contact($two, $guests[1], 'UNREACHABLE', '2026-11-02T09:00');
        $this->contact($two, $guests[1], 'UNREACHABLE', '2026-11-02T10:00');

        return [$admin, $one, $two, $guests];
    }

    public function test_current_status_counts_and_distinct_contact_success_reconcile(): void
    {
        [$admin] = $this->cohort();
        $stats = app(FollowUpReports::class)->summary($admin, []);
        $this->assertSame(['total' => 3, 'assigned' => 2, 'unassigned' => 1, 'not_contacted' => 1,
            'contacted' => 1, 'unreachable' => 1, 'attempts' => 4, 'attempted_guests' => 2,
            'successful_guests' => 1, 'notes' => 1, 'success_rate' => 50.0], $stats);
        $this->assertSame($stats['total'], $stats['assigned'] + $stats['unassigned']);
        $this->assertSame(3, app(FollowUpReports::class)->sessionBreakdown($admin, [])->sum('guest_total'));
        $this->actingAs($admin)->get(route('follow-up.reports'))->assertOk()->assertSee('50%')->assertSee('Activity timeline');
    }

    public function test_reassignment_counts_current_workload_once_and_credits_historical_activity_to_actor(): void
    {
        [$admin, $one, $two, $guests] = $this->cohort();
        $this->assign($admin, $guests[0], $two);
        $reports = app(FollowUpReports::class);
        $performance = $reports->officers($admin, [])->keyBy('id');
        $this->assertSame(0, $performance[$one->id]['assigned']);
        $this->assertSame(2, $performance[$two->id]['assigned']);
        $this->assertSame(2, $performance[$one->id]['attempts']);
        $this->assertSame(1, $performance[$one->id]['successful_guests']);
        $this->assertSame(2, $performance->sum('assigned'));
        $this->assertSame(1, $performance[$two->id]['untouched']);
        $personal = $reports->summary($two, []);
        $this->assertSame(2, $personal['total']);
        $this->assertSame(2, $personal['attempts']);
        $this->assertSame(1, $personal['attempted_guests']);
        $this->assertSame(0, $personal['successful_guests']);
        $this->assertSame(0, $reports->summary($one, [])['total']);
    }

    public function test_period_filters_use_wat_boundaries_without_changing_current_guest_cohort(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create();
        $this->contact($admin, $guest, 'UNREACHABLE', '2026-11-02T00:30');
        $this->contact($admin, $guest, 'CONTACTED', '2026-11-03T00:30');
        $reports = app(FollowUpReports::class);
        $day = $reports->summary($admin, ['activity_from' => '2026-11-02', 'activity_to' => '2026-11-02']);
        $this->assertSame(1, $day['attempts']);
        $this->assertSame(0, $day['successful_guests']);
        $this->assertSame(1, $day['contacted']);
        $this->assertSame(1, $day['total']);
        $this->assertDatabaseHas('follow_up_activities', ['attempted_at_utc' => '2026-11-01 23:30:00']);
        $this->assertSame(0, $reports->summary($admin, ['activity_to' => '2026-11-01'])['attempts']);
        $this->actingAs($admin)->get(route('follow-up.reports', ['activity_to' => '2026-11-02']))->assertOk();
        $this->get(route('follow-up.reports', ['activity_from' => '2026-11-03', 'activity_to' => '2026-11-02']))->assertSessionHasErrors('activity_to');
    }

    public function test_guest_filters_session_day_status_officer_assignment_and_source_are_consistent(): void
    {
        [$admin, $one, $two, $guests] = $this->cohort();
        $session = ConferenceSession::where('code', '2026-10-31-AM')->firstOrFail();
        $guests[0]->update(['conference_session_id' => $session->id]);
        $reports = app(FollowUpReports::class);
        $this->assertSame(1, $reports->summary($admin, ['session' => $session->id])['total']);
        $this->assertSame(1, $reports->summary($admin, ['day' => 5])['total']);
        $this->assertSame(1, $reports->summary($admin, ['status' => 'CONTACTED'])['total']);
        $this->assertSame(1, $reports->summary($admin, ['officer' => $one->id])['total']);
        $this->assertSame(1, $reports->summary($admin, ['assignment' => 'unassigned'])['total']);
        $this->assertSame(3, $reports->summary($admin, ['source' => 'manual'])['total']);
        $this->assertSame(0, $reports->summary($admin, ['source' => 'import'])['total']);
        $this->assertSame(1, $reports->summary($admin, ['encounter_from' => '2026-10-31', 'encounter_to' => '2026-10-31'])['total']);
        $this->assertSame(2, $reports->summary($admin, ['activity_actor' => $one->id])['attempts']);
        $this->actingAs($admin)->get(route('guests.index', ['officer' => $one->id]))->assertViewHas('guests', fn ($page) => $page->total() === 1);
    }

    public function test_historical_and_archived_guests_do_not_leak_into_current_report(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        GuestRecord::factory()->create(['full_name' => 'Current guest']);
        $event = ConferenceEvent::create(['name' => 'SUMMIT 2025', 'year' => 2025, 'starts_on' => '2025-10-28', 'ends_on' => '2025-10-28']);
        $session = ConferenceSession::create(['conference_event_id' => $event->id, 'code' => 'historic-report',
            'day_number' => 1, 'date_local' => '2025-10-28', 'name' => 'General', 'label' => 'Historical session']);
        GuestRecord::factory()->create(['full_name' => 'Historical guest', 'conference_session_id' => $session->id]);
        $archived = GuestRecord::factory()->create(['full_name' => 'Archived guest']);
        $archived->forceFill(['archived_at' => now()])->save();
        $reports = app(FollowUpReports::class);
        $this->assertSame(1, $reports->summary($admin, [])['total']);
        $this->assertSame(1, $reports->summary($admin, ['year' => 2025])['total']);
        $this->assertSame(1, $reports->summary($admin, ['archive' => 'archived'])['total']);
        $this->actingAs($admin)->get(route('follow-up.reports'))->assertSee('Current guest')->assertDontSee('Historical guest')->assertDontSee('Archived guest');
    }

    public function test_officer_reports_and_all_export_types_remain_personal_even_with_forged_filters(): void
    {
        [$admin, $one, $two, $guests] = $this->cohort();
        $guests[0]->update(['full_name' => 'Visible personal guest']);
        $guests[1]->update(['full_name' => 'Private other guest']);
        $this->actingAs($one)->get(route('follow-up.reports'))->assertSee('Visible personal guest')->assertDontSee('Private other guest')->assertDontSee('Officer Two');
        foreach (['guests', 'assignments', 'status', 'officers', 'activities'] as $type) {
            $response = $this->get(route('follow-up.reports.export', ['type' => $type]))->assertOk()->assertDownload();
            $this->assertStringNotContainsString('Private other guest', $response->streamedContent());
            $this->assertStringNotContainsString('Officer Two', $response->streamedContent());
        }
        $this->get(route('follow-up.reports', ['officer' => $two->id]))->assertViewHas('stats', fn ($stats) => $stats['total'] === 0);
        $this->get(route('follow-up.reports.export', ['type' => 'activities', 'activity_actor' => $two->id]))
            ->assertOk();
    }

    public function test_exports_escape_formula_injection_and_include_only_filtered_roster(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create(['full_name' => '=Formula Guest']);
        GuestRecord::factory()->create(['full_name' => 'Exclude this guest', 'phone_normalized' => '+2348099999999']);
        $this->contact($admin, $guest, 'NOTE_ONLY', '2026-11-02T10:00', '=HYPERLINK("untrusted")');
        $this->actingAs($admin);
        $roster = $this->get(route('follow-up.reports.export', ['type' => 'guests', 'search' => '=Formula Guest']))->assertOk();
        $this->assertStringContainsString("'=Formula Guest", $roster->streamedContent());
        $this->assertStringNotContainsString('Exclude this guest', $roster->streamedContent());
        $activities = $this->get(route('follow-up.reports.export', ['type' => 'activities']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $activities);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported', 'actor_user_id' => $admin->id]);
    }

    public function test_empty_denominator_and_unauthorized_roles_are_handled(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $stats = app(FollowUpReports::class)->summary($admin, []);
        $this->assertNull($stats['success_rate']);
        $this->actingAs($admin)->get(route('follow-up.reports'))->assertOk()->assertSee('n/a');
        foreach (['data_entry', 'info_com'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('follow-up.reports'))->assertForbidden();
            $this->get(route('follow-up.reports.export', ['type' => 'guests']))->assertForbidden();
        }
    }

    public function test_report_import_source_and_inactive_officer_workload_remain_visible_to_admin(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $batch = GuestImportBatch::create(['conference_event_id' => $guest->session->conference_event_id,
            'filename' => 'synthetic.csv', 'sha256' => hash('sha256', 'synthetic'), 'uploaded_by_user_id' => $admin->id]);
        $row = GuestImportRow::create(['batch_id' => $batch->id, 'row_number' => 2, 'worksheet_name' => 'CSV',
            'raw_json' => [], 'row_fingerprint' => hash('sha256', 'row')]);
        $guest->update(['import_row_id' => $row->id]);
        $this->assign($admin, $guest, $officer);
        $officer->forceFill(['is_active' => false])->save();
        $this->assertSame(1, app(FollowUpReports::class)->summary($admin, ['source' => 'import'])['total']);
        $this->assertSame(1, app(FollowUpReports::class)->officers($admin, [])->firstWhere('id', $officer->id)['assigned']);
        $this->actingAs($admin)->get(route('follow-up.reports', ['officer' => $officer->id]))->assertOk()->assertSee($officer->name);
    }
}
