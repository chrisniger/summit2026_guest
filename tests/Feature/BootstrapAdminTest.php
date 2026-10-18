<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class BootstrapAdminTest extends TestCase
{
    public function test_bootstrap_uses_hidden_password_and_creates_initial_admin(): void
    {
        $this->artisan('summit:bootstrap-admin', ['--name' => 'Initial Admin', '--email' => 'admin@example.test'])
            ->expectsQuestion('Password (at least 8 characters)', 'ConferencePass123')
            ->expectsQuestion('Confirm password', 'ConferencePass123')
            ->assertSuccessful();
        $this->assertTrue(User::where('email', 'admin@example.test')->firstOrFail()->hasRole('super_admin'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.bootstrapped']);
    }

    public function test_bootstrap_refuses_existing_admin(): void
    {
        $this->userWithRole('super_admin');
        $this->artisan('summit:bootstrap-admin')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_bootstrap_rejects_password_shorter_than_eight_characters(): void
    {
        $this->artisan('summit:bootstrap-admin', ['--name' => 'Initial Admin', '--email' => 'admin@example.test'])
            ->expectsQuestion('Password (at least 8 characters)', 'weak')
            ->expectsQuestion('Confirm password', 'weak')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
