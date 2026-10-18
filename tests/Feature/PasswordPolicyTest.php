<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    public static function passwords(): array
    {
        return [
            'lowercase only' => ['abcdefgh', true],
            'uppercase only' => ['ABCDEFGH', true],
            'numbers only' => ['12345678', true],
            'symbols only' => ['!@#$%^&*', true],
            'seven characters' => ['abcdefg', false],
        ];
    }

    #[DataProvider('passwords')]
    public function test_staff_creation_requires_only_eight_characters(string $password, bool $accepted): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $response = $this->post('/admin/staff', [
            'name' => 'Password Policy Test', 'email' => 'policy@example.test', 'role' => 'officer',
            'password' => $password, 'password_confirmation' => $password,
        ]);

        if ($accepted) {
            $response->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check($password, User::where('email', 'policy@example.test')->firstOrFail()->password));
        } else {
            $response->assertSessionHasErrors('password');
            $this->assertDatabaseMissing('users', ['email' => 'policy@example.test']);
        }
    }

    #[DataProvider('passwords')]
    public function test_password_reset_requires_only_eight_characters(string $password, bool $accepted): void
    {
        $user = $this->userWithRole('officer');
        $response = $this->post('/reset-password', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => $password, 'password_confirmation' => $password,
        ]);

        if ($accepted) {
            $response->assertRedirect('/login')->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check($password, $user->fresh()->password));
        } else {
            $response->assertSessionHasErrors('password');
        }
    }

    #[DataProvider('passwords')]
    public function test_initial_admin_requires_only_eight_characters(string $password, bool $accepted): void
    {
        $command = $this->artisan('summit:bootstrap-admin', ['--name' => 'Policy Test', '--email' => 'policy@example.test'])
            ->expectsQuestion('Password (at least 8 characters)', $password)
            ->expectsQuestion('Confirm password', $password);

        if ($accepted) {
            $command->assertSuccessful()->run();
            $this->assertTrue(Hash::check($password, User::where('email', 'policy@example.test')->firstOrFail()->password));
        } else {
            $command->assertFailed()->run();
            $this->assertDatabaseCount('users', 0);
        }
    }
}
