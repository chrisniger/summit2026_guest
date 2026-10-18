<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdmin extends Command
{
    protected $signature = 'summit:bootstrap-admin {--name=} {--email=}';

    protected $description = 'Create the initial Super Admin using a hidden password prompt; refuses if an administrator exists.';

    public function handle(): int
    {
        if (User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->exists()) {
            $this->error('A Super Admin already exists. Use staff administration.');

            return self::FAILURE;
        }
        $data = [
            'name' => $this->option('name') ?: $this->ask('Full name'),
            'email' => strtolower(trim($this->option('email') ?: $this->ask('Email address'))),
            'password' => $this->secret('Password (at least 8 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', Password::defaults()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $role = Role::where('name', 'super_admin')->firstOrFail();
            $user = User::create(collect($data)->only('name', 'email', 'password')->all());
            $user->roles()->attach($role);
            app(Audit::class)->record('platform', 'staff.bootstrapped', $user, $user, [], ['role' => 'super_admin']);
        });
        $this->info('Super Admin created. Sign in using the credentials you entered.');

        return self::SUCCESS;
    }
}
