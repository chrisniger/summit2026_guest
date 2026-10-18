<?php

namespace Tests\Feature;

use App\Models\FollowUpAssignment;
use App\Models\GuestRecord;
use App\Models\InfoAttendee;
use App\Models\User;
use App\Services\FollowUpAssignments;
use Tests\TestCase;

class FollowUpAssignmentTest extends TestCase
{
    private function selection(User $admin, array $ids, ?User $officer, string $action = 'assign'): array
    {
        return app(FollowUpAssignments::class)->preview($admin, ['guest_ids' => $ids, 'officer_user_id' => $officer?->id,
            'action' => $action, 'reason' => 'Reviewed session allocation']);
    }

    public function test_twenty_guest_assignment_requires_confirmation_and_saves_exact_selected_scope(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guests = GuestRecord::factory()->count(20)->create();
        $other = GuestRecord::factory()->create();
        InfoAttendee::factory()->create();
        $this->actingAs($admin)->post(route('follow-up.assignments.preview'), ['guest_ids' => $guests->modelKeys(), 'officer_user_id' => $officer->id,
            'action' => 'assign', 'reason' => 'Reviewed allocation'])->assertOk()->assertSee('20 selected encounters');
        $this->assertDatabaseCount('follow_up_assignments', 0);
        $preview = $this->selection($admin, $guests->modelKeys(), $officer);
        $this->post(route('follow-up.assignments.commit'), ['token' => $preview['token']])->assertSessionHasErrors('confirm');
        $this->post(route('follow-up.assignments.commit'), ['token' => $preview['token'], 'confirm' => 1])->assertSessionHasNoErrors()->assertRedirect(route('guests.index'));
        $this->assertDatabaseCount('follow_up_assignments', 20);
        $this->assertSame(20, FollowUpAssignment::where('officer_user_id', $officer->id)->whereNull('unassigned_at')->count());
        $this->assertNull($other->activeAssignment);
        $this->assertSame(20, GuestRecord::where('current_status', 'NOT CONTACTED')->whereIn('id', $guests->modelKeys())->count());
        $this->assertDatabaseCount('info_attendees', 1);
        $this->assertDatabaseCount('info_attendance_records', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assignment.created', 'actor_user_id' => $admin->id]);
    }

    public function test_reassignment_closes_old_history_and_revokes_former_officer_access(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $one = $this->userWithRole('officer');
        $two = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $service = app(FollowUpAssignments::class);
        $service->commit($admin, $this->selection($admin, [$guest->id], $one)['token']);
        $old = $guest->activeAssignment()->firstOrFail();
        $this->actingAs($one)->get(route('guests.show', $guest))->assertOk();
        $service->commit($admin, $this->selection($admin, [$guest->id], $two, 'reassign')['token']);
        $this->assertNotNull($old->fresh()->unassigned_at);
        $this->assertSame('Reviewed session allocation', $old->fresh()->reason);
        $this->assertSame('Reviewed session allocation', $old->fresh()->closure_reason);
        $this->assertSame($two->id, $guest->activeAssignment()->first()->officer_user_id);
        $this->assertDatabaseCount('follow_up_assignments', 2);
        $this->actingAs($one)->get(route('guests.show', $guest))->assertForbidden();
        $this->actingAs($two)->get(route('guests.show', $guest))->assertOk();
    }

    public function test_unassignment_preserves_history_and_current_status(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $guest->forceFill(['current_status' => 'CONTACTED'])->save();
        $service = app(FollowUpAssignments::class);
        $service->commit($admin, $this->selection($admin, [$guest->id], $officer)['token']);
        $result = $service->commit($admin, $this->selection($admin, [$guest->id], null, 'unassign')['token']);
        $this->assertSame(1, $result['unassigned']);
        $this->assertNull($guest->activeAssignment()->first());
        $this->assertSame('CONTACTED', $guest->fresh()->current_status);
        $this->assertDatabaseCount('follow_up_assignments', 1);
    }

    public function test_stale_selection_rejects_entire_bulk_change_and_replay_creates_no_history(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guests = GuestRecord::factory()->count(2)->create();
        $preview = $this->selection($admin, $guests->modelKeys(), $officer);
        $guests->last()->forceFill(['version' => 2])->save();
        $this->actingAs($admin)->post(route('follow-up.assignments.commit'), ['token' => $preview['token'], 'confirm' => 1])->assertStatus(409);
        $this->assertDatabaseCount('follow_up_assignments', 0);
        $preview = $this->selection($admin, $guests->modelKeys(), $officer);
        $this->post(route('follow-up.assignments.commit'), ['token' => $preview['token'], 'confirm' => 1])->assertRedirect();
        $this->post(route('follow-up.assignments.commit'), ['token' => $preview['token'], 'confirm' => 1])->assertStatus(409);
        $this->assertDatabaseCount('follow_up_assignments', 2);
    }

    public function test_inactive_or_role_changed_assignee_cannot_be_confirmed(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $preview = $this->selection($admin, [$guest->id], $officer);
        $officer->forceFill(['is_active' => false])->save();
        $this->actingAs($admin)->post(route('follow-up.assignments.commit'), ['token' => $preview['token'], 'confirm' => 1])->assertSessionHasErrors('officer_user_id');
        $officer->forceFill(['is_active' => true])->save();
        $officer->roles()->detach();
        $this->post(route('follow-up.assignments.commit'), ['token' => $preview['token'], 'confirm' => 1])->assertSessionHasErrors('officer_user_id');
        $this->assertDatabaseCount('follow_up_assignments', 0);
    }

    public function test_confirmation_is_actor_bound_expires_and_rejects_tampering(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $preview = $this->selection($admin, [GuestRecord::factory()->create()->id], $officer);
        $this->actingAs($this->userWithRole('follow_up_admin'))->post(route('follow-up.assignments.commit'),
            ['token' => $preview['token'], 'confirm' => 1])->assertStatus(409);
        $this->actingAs($admin)->post(route('follow-up.assignments.commit'), ['token' => 'altered', 'confirm' => 1])->assertSessionHasErrors('confirmation');
        $this->travel(16)->minutes();
        $this->post(route('follow-up.assignments.commit'), ['token' => $preview['token'], 'confirm' => 1])->assertStatus(409);
        $this->assertDatabaseCount('follow_up_assignments', 0);
    }

    public function test_data_entry_officer_and_info_roles_cannot_assign(): void
    {
        $guest = GuestRecord::factory()->create();
        foreach (['data_entry', 'officer', 'info_com'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('follow-up.assignments.preview'), ['guest_ids' => [$guest->id]])->assertForbidden();
            $this->post(route('follow-up.assignments.commit'), ['token' => 'x', 'confirm' => 1])->assertForbidden();
        }
    }

    public function test_archived_unknown_duplicate_selection_and_wrong_assignee_are_rejected(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $guest = GuestRecord::factory()->create();
        $this->actingAs($admin);
        $base = ['guest_ids' => [$guest->id], 'action' => 'assign', 'reason' => 'Reviewed allocation',
            'officer_user_id' => $this->userWithRole('data_entry')->id];
        $this->post(route('follow-up.assignments.preview'), $base)->assertSessionHasErrors('officer_user_id');
        $base['officer_user_id'] = $this->userWithRole('officer')->id;
        $this->post(route('follow-up.assignments.preview'), [...$base, 'guest_ids' => [$guest->id, $guest->id]])->assertSessionHasErrors();
        $this->post(route('follow-up.assignments.preview'), [...$base, 'guest_ids' => [999999]])->assertForbidden();
        $guest->forceFill(['archived_at' => now()])->save();
        $this->post(route('follow-up.assignments.preview'), $base)->assertForbidden();
    }

    public function test_assign_does_not_silently_replace_ownership_and_same_officer_reassign_is_unchanged(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $officer = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $service = app(FollowUpAssignments::class);
        $service->commit($admin, $this->selection($admin, [$guest->id], $officer)['token']);
        $this->actingAs($admin)->post(route('follow-up.assignments.preview'), ['guest_ids' => [$guest->id],
            'action' => 'assign', 'officer_user_id' => $officer->id, 'reason' => 'Reviewed again'])->assertSessionHasErrors('action');
        $result = $service->commit($admin, $this->selection($admin, [$guest->id], $officer, 'reassign')['token']);
        $this->assertSame(1, $result['unchanged']);
        $this->assertDatabaseCount('follow_up_assignments', 1);
    }
}
