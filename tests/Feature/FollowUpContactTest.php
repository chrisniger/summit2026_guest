<?php

namespace Tests\Feature;

use App\Models\FollowUpActivity;
use App\Models\FollowUpStatusHistory;
use App\Models\GuestRecord;
use App\Models\User;
use App\Services\FollowUpAssignments;
use App\Services\FollowUpContact;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class FollowUpContactTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(12, 0));
    }

    private function assign(User $admin, GuestRecord $guest, User $officer): void
    {
        $service = app(FollowUpAssignments::class);
        $preview = $service->preview($admin, ['guest_ids' => [$guest->id], 'action' => 'reassign', 'officer_user_id' => $officer->id, 'reason' => 'Reviewed allocation']);
        $service->commit($admin, $preview['token']);
    }

    private function input(GuestRecord $guest, array $extra = []): array
    {
        return ['submission_id' => (string) Str::uuid(), 'version' => $guest->fresh()->version,
            'assignment_id' => $guest->activeAssignment()->first()?->id, 'method' => 'call', 'outcome' => 'UNREACHABLE',
            'attempted_at' => '2026-11-02T10:00', 'comment' => 'Actual call was not answered.', 'failed_attempt_confirmed' => 1, ...$extra];
    }

    public function test_failed_then_successful_attempt_keeps_history_and_note_does_not_change_status(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $this->assign($admin, $guest, $officer);
        $this->actingAs($officer)->post(route('follow-up.activities.store', $guest), $this->input($guest))->assertSessionHasNoErrors();
        $this->assertSame('UNREACHABLE', $guest->fresh()->current_status);
        $this->post(route('follow-up.activities.store', $guest), $this->input($guest, ['outcome' => 'CONTACTED', 'two_way_confirmed' => 1,
            'attempted_at' => '2026-11-02T11:00', 'comment' => 'Spoke with guest and received a reply.']))->assertSessionHasNoErrors();
        $this->assertSame('CONTACTED', $guest->fresh()->current_status);
        $this->post(route('follow-up.activities.store', $guest), $this->input($guest, ['outcome' => 'NOTE_ONLY', 'comment' => 'SMS sent; no reply yet.']))->assertSessionHasNoErrors();
        $this->assertSame('CONTACTED', $guest->fresh()->current_status);
        $this->assertDatabaseCount('follow_up_activities', 3);
        $this->assertDatabaseCount('follow_up_status_history', 2);
        $this->assertDatabaseHas('follow_up_activities', ['attempted_at_utc' => '2026-11-02 09:00:00', 'outcome' => 'UNREACHABLE']);
        $this->get(route('guests.show', $guest))->assertSee('Actual call was not answered.')->assertSee('Status history');
        $this->assertDatabaseCount('info_attendees', 0);
        $this->assertDatabaseCount('info_attendance_records', 0);
    }

    public function test_double_submission_is_idempotent_but_stale_new_attempt_is_rejected(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $this->assign($admin, $guest, $officer);
        $data = $this->input($guest);
        $this->actingAs($officer)->post(route('follow-up.activities.store', $guest), $data)->assertSessionHasNoErrors();
        $this->post(route('follow-up.activities.store', $guest), $data)->assertSessionHasNoErrors();
        $this->post(route('follow-up.activities.store', $guest), [...$data, 'submission_id' => (string) Str::uuid()])->assertStatus(409);
        $this->assertDatabaseCount('follow_up_activities', 1);
        $this->assertDatabaseCount('follow_up_status_history', 1);
    }

    public function test_stale_assignment_is_rejected_even_when_reassigned_back_to_same_officer(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $one = $this->userWithRole('officer');
        $two = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $this->assign($admin, $guest, $one);
        $data = $this->input($guest);
        $this->assign($admin, $guest, $two);
        $this->actingAs($one)->post(route('follow-up.activities.store', $guest), $data)->assertForbidden();
        $this->assign($admin, $guest, $one);
        $this->post(route('follow-up.activities.store', $guest), [...$data, 'version' => $guest->fresh()->version])->assertStatus(409);
        $this->assertDatabaseCount('follow_up_activities', 0);
    }

    public function test_prior_officer_private_comments_and_history_are_hidden_from_new_officer(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $one = $this->userWithRole('officer');
        $two = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $this->assign($admin, $guest, $one);
        app(FollowUpContact::class)->record($one, $guest, $this->input($guest, ['comment' => 'Private prior-officer comment']));
        $this->assign($admin, $guest, $two);
        $this->actingAs($two)->get(route('guests.show', $guest))->assertOk()->assertDontSee('Private prior-officer comment');
        $this->get(route('follow-up.reports.export', ['type' => 'activities']))->assertStreamedContent("\xEF\xBB\xBF".'"Activity ID","Guest ID",Guest,"Encounter date",Session,Actor,Method,"Attempt time (WAT)",Outcome,"Status before entry","Status after entry",Comment'."\n");
        $this->actingAs($admin)->get(route('guests.show', $guest))->assertSee('Private prior-officer comment');
        $this->actingAs($one)->get(route('guests.show', $guest))->assertForbidden();
    }

    public function test_contacted_requires_explicit_two_way_contact_and_unreachable_requires_failed_attempt(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create();
        $this->actingAs($admin)->post(route('follow-up.activities.store', $guest), $this->input($guest,
            ['outcome' => 'CONTACTED']))->assertSessionHasErrors('two_way_confirmed');
        $this->post(route('follow-up.activities.store', $guest), $this->input($guest, ['failed_attempt_confirmed' => 0]))->assertSessionHasErrors('failed_attempt_confirmed');
        $this->assertDatabaseCount('follow_up_activities', 0);
    }

    public function test_earlier_attempt_does_not_overwrite_a_later_outcome(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create();
        app(FollowUpContact::class)->record($admin, $guest, $this->input($guest, ['outcome' => 'CONTACTED', 'two_way_confirmed' => 1, 'attempted_at' => '2026-11-02T11:00']));
        $activity = app(FollowUpContact::class)->record($admin, $guest, $this->input($guest));
        $this->assertSame('UNREACHABLE', $activity->outcome);
        $this->assertSame('CONTACTED', $activity->new_status);
        $this->assertSame('CONTACTED', $guest->fresh()->current_status);
        $this->assertDatabaseCount('follow_up_activities', 2);
        $this->assertDatabaseHas('follow_up_status_history', ['reason' => 'Earlier attempt recorded; later outcome retained.']);
    }

    public function test_future_ambiguous_times_invalid_methods_and_blank_comments_are_rejected(): void
    {
        $guest = GuestRecord::factory()->create();
        $this->actingAs($this->userWithRole('follow_up_admin'));
        foreach ([['attempted_at' => '2099-01-01T12:00'], ['attempted_at' => '02/11/2026 10:00'], ['method' => 'email'],
            ['outcome' => 'SMS_SENT'], ['comment' => ''], ['attempted_at' => '2026-02-30T12:00']] as $bad) {
            $this->post(route('follow-up.activities.store', $guest), $this->input($guest, $bad))->assertSessionHasErrors(array_key_first($bad));
        }
        $this->assertDatabaseCount('follow_up_activities', 0);
    }

    public function test_data_entry_info_other_officers_and_archived_records_cannot_be_updated(): void
    {
        $guest = GuestRecord::factory()->create();
        foreach (['data_entry', 'info_com', 'officer'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('follow-up.activities.store', $guest), $this->input($guest))->assertForbidden();
        }
        $guest->forceFill(['archived_at' => now()])->save();
        $this->actingAs($this->userWithRole('follow_up_admin'))->post(route('follow-up.activities.store', $guest), $this->input($guest))->assertForbidden();
    }

    public function test_posted_status_creator_and_assignment_ids_do_not_override_authoritative_data(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create();
        $this->actingAs($admin)->post(route('follow-up.activities.store', $guest), $this->input($guest,
            ['actor_user_id' => 999999, 'new_status' => 'CONTACTED', 'current_status' => 'CONTACTED', 'guest_record_id' => 999999]))->assertSessionHasNoErrors();
        $activity = FollowUpActivity::firstOrFail();
        $this->assertSame($admin->id, $activity->actor_user_id);
        $this->assertSame($guest->id, $activity->guest_record_id);
        $this->assertSame('UNREACHABLE', $activity->new_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact.recorded', 'actor_user_id' => $admin->id]);
    }

    public function test_transaction_rolls_back_activity_status_history_and_version_on_failure(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create();
        Event::listen('eloquent.created: '.FollowUpStatusHistory::class, function (): void {
            throw new \RuntimeException('Synthetic interrupted transaction');
        });
        try {
            app(FollowUpContact::class)->record($admin, $guest, $this->input($guest));
            $this->fail('Expected transaction failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic interrupted transaction', $error->getMessage());
        }
        $this->assertDatabaseCount('follow_up_activities', 0);
        $this->assertDatabaseCount('follow_up_status_history', 0);
        $this->assertSame('NOT CONTACTED', $guest->fresh()->current_status);
        $this->assertSame(1, $guest->fresh()->version);
    }

    public function test_contact_service_rechecks_deactivated_actor_even_with_stale_user_instance(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create();
        User::whereKey($admin->id)->update(['is_active' => false]);
        try {
            app(FollowUpContact::class)->record($admin, $guest, $this->input($guest));
            $this->fail('Expected revoked access.');
        } catch (HttpExceptionInterface $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertDatabaseCount('follow_up_activities', 0);
    }
}
