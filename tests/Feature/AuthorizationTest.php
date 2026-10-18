<?php

namespace Tests\Feature;

use App\Livewire\SessionManager;
use App\Models\FollowUpAssignment;
use App\Models\GuestRecord;
use App\Models\InfoAttendee;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_each_role_redirects_to_its_workspace_and_renders(): void
    {
        foreach (['super_admin' => '/admin', 'info_com' => '/info-com', 'follow_up_admin' => '/follow-up', 'data_entry' => '/guest-service', 'officer' => '/my-guests'] as $role => $url) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/dashboard')->assertRedirect($url);
            $this->get($url)->assertOk()->assertSee('summit-logo.png');
        }
    }

    public function test_cross_module_access_is_denied(): void
    {
        $this->actingAs($this->userWithRole('info_com'));
        foreach (['/follow-up', '/guest-service', '/my-guests', '/admin', '/admin/staff', '/admin/sessions', '/admin/audit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->actingAs($this->userWithRole('follow_up_admin'))->get('/info-com')->assertForbidden();
        $this->post('/admin/staff', [])->assertForbidden();
    }

    public function test_super_admin_can_open_all_workspaces_and_admin_pages(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        foreach (['/admin', '/info-com', '/follow-up', '/guest-service', '/my-guests', '/admin/staff', '/admin/sessions', '/admin/audit'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guest_redirects_and_user_without_role_has_no_workspace(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/access-denied');
        $this->get('/access-denied')->assertForbidden()->assertSee('workspace is restricted');
    }

    public function test_officer_ownership_scope_and_reassignment_revoke_access(): void
    {
        $admin = $this->userWithRole('follow_up_admin');
        $one = $this->userWithRole('officer');
        $two = $this->userWithRole('officer');
        $guest = GuestRecord::factory()->create();
        $assignment = FollowUpAssignment::create(['guest_record_id' => $guest->id, 'officer_user_id' => $one->id, 'assigned_by_user_id' => $admin->id, 'assigned_at' => now()]);
        $this->assertTrue(Gate::forUser($one)->allows('view', $guest));
        $this->assertFalse(Gate::forUser($two)->allows('view', $guest));
        $this->assertFalse(Gate::forUser($two)->allows('contact', $guest));
        $this->assertSame(0, GuestRecord::visibleTo($two)->count());
        $assignment->update(['unassigned_at' => now()]);
        FollowUpAssignment::create(['guest_record_id' => $guest->id, 'officer_user_id' => $two->id, 'assigned_by_user_id' => $admin->id, 'assigned_at' => now()]);
        $this->assertFalse(Gate::forUser($one)->allows('view', $guest));
        $this->assertTrue(Gate::forUser($two)->allows('contact', $guest));
    }

    public function test_data_entry_cannot_assign_contact_or_read_others_entries(): void
    {
        $entry = $this->userWithRole('data_entry');
        $own = GuestRecord::factory()->create(['created_by_user_id' => $entry->id]);
        $other = GuestRecord::factory()->create();
        $this->assertTrue(Gate::forUser($entry)->allows('update', $own));
        $this->assertFalse(Gate::forUser($entry)->allows('update', $other));
        $this->assertFalse(Gate::forUser($entry)->allows('contact', $own));
        $this->assertFalse(Gate::forUser($entry)->allows('create', FollowUpAssignment::class));
        $this->assertFalse(Gate::forUser($entry)->allows('create', InfoAttendee::class));
    }

    public function test_optional_import_permissions_are_not_implicitly_granted(): void
    {
        foreach (['info_com', 'follow_up_admin', 'data_entry', 'officer'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertFalse($user->hasPermission('info.import'));
            $this->assertFalse($user->hasPermission('guest.import'));
        }
        $user->permissions()->attach(Permission::where('name', 'guest.import')->firstOrFail());
        $this->assertTrue($user->hasPermission('guest.import'));
    }

    public function test_livewire_session_actions_are_protected(): void
    {
        Livewire::actingAs($this->userWithRole('info_com'))->test(SessionManager::class)->assertForbidden();
    }

    public function test_officer_dashboard_counts_only_current_assignments(): void
    {
        $officer = $this->userWithRole('officer');
        GuestRecord::factory()->create();
        $this->actingAs($officer)->get('/my-guests')->assertOk()->assertViewHas('guests', fn ($stats) => $stats['total'] === 0);
    }

    public function test_super_admin_personal_workspaces_still_show_personal_counts(): void
    {
        $admin = $this->userWithRole('super_admin');
        GuestRecord::factory()->create();
        GuestRecord::factory()->create(['created_by_user_id' => $admin->id]);
        $this->actingAs($admin)->get('/admin')->assertViewHas('guests', fn ($stats) => $stats['total'] === 2);
        $this->get('/guest-service')->assertViewHas('guests', fn ($stats) => $stats['total'] === 1);
        $this->get('/my-guests')->assertViewHas('guests', fn ($stats) => $stats['total'] === 0);
    }
}
