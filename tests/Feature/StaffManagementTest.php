<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    public function test_admin_can_create_account_with_one_authorized_role(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin)->post('/admin/staff', ['name' => 'Test Officer', 'email' => 'officer@example.test', 'role' => 'officer', 'password' => 'ConferencePass123', 'password_confirmation' => 'ConferencePass123'])->assertSessionHasNoErrors();
        $user = User::where('email', 'officer@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('officer'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created', 'subject_id' => $user->id]);
        $this->assertStringNotContainsString('ConferencePass123', AuditLog::latest('id')->first()->toJson());
    }

    public function test_non_admin_cannot_create_or_update_staff(): void
    {
        $user = $this->userWithRole('data_entry');
        $target = $this->userWithRole('officer');
        $this->actingAs($user)->post('/admin/staff', [])->assertForbidden();
        $this->patch('/admin/staff/'.$target->id, ['role' => 'super_admin', 'is_active' => 1])->assertForbidden();
        $this->assertTrue($target->fresh()->hasRole('officer'));
    }

    public function test_admin_can_deactivate_staff_and_self_change_is_rejected(): void
    {
        $admin = $this->userWithRole('super_admin');
        $officer = $this->userWithRole('officer');
        $this->actingAs($admin)->patch('/admin/staff/'.$officer->id, ['role' => 'officer', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($officer->fresh()->is_active);
        $this->patch('/admin/staff/'.$admin->id, ['role' => 'officer', 'is_active' => 0])->assertSessionHasErrors('staff');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_invalid_role_and_weak_initial_password_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->post('/admin/staff', ['name' => 'Test', 'email' => 'test@example.test', 'role' => 'unknown', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors(['role', 'password']);
    }
}
