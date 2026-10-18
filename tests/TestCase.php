<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if ($app->make('config')->get('database.default') !== 'mysql'
            || $app->make('config')->get('database.connections.mysql.database') !== 'summit_2026_test') {
            throw new \RuntimeException('Tests must use the dedicated summit_2026_test MariaDB database.');
        }

        return $app;
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }
}
