<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_has_brand_and_no_public_registration(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back')->assertSee('summit-logo.png')->assertDontSee('Create an account');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_successful_login_rotates_session_and_records_audit(): void
    {
        $user = $this->userWithRole('info_com', ['password' => 'ConferencePass123']);
        $this->get('/login');
        $old = session()->getId();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'ConferencePass123'])->assertRedirect(route('info'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotEquals($old, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_user_id' => $user->id]);
    }

    public function test_password_is_hashed_and_invalid_credentials_rejected(): void
    {
        $user = $this->userWithRole('officer', ['password' => 'ConferencePass123']);
        $this->assertTrue(Hash::check('ConferencePass123', $user->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $this->freezeTime();
        $user = $this->userWithRole('info_com');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login_and_active_session_is_revoked(): void
    {
        $user = $this->userWithRole('super_admin', ['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_invalidates_authentication(): void
    {
        $user = $this->userWithRole('data_entry');
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_reset_request_is_generic_and_sends_notification(): void
    {
        Notification::fake();
        $user = $this->userWithRole('officer');
        $response = $this->post('/forgot-password', ['email' => $user->email]);
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $known = session('status');
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertSessionHas('status', $known);
    }

    public function test_inactive_account_does_not_receive_reset_link(): void
    {
        Notification::fake();
        $user = $this->userWithRole('officer', ['is_active' => false]);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_valid_password_reset_and_token_cannot_be_reused(): void
    {
        $user = $this->userWithRole('officer');
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'NewConference123', 'password_confirmation' => 'NewConference123'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewConference123', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'subject_id' => $user->id]);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_invalid_reset_token_and_weak_password_are_rejected(): void
    {
        $user = $this->userWithRole('officer');
        $this->post('/reset-password', ['email' => $user->email, 'token' => 'invalid', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123'])->assertSessionHasErrors('email');
        $this->post('/reset-password', ['email' => $user->email, 'token' => Password::createToken($user), 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    }

    public function test_csrf_protection_rejects_browser_write_without_token(): void
    {
        $this->app['env'] = 'local';
        $this->post('/login', ['email' => 'staff@example.test', 'password' => 'invalid'])->assertStatus(419);
    }
}
