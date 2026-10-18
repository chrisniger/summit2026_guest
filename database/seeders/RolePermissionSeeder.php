<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('summit.roles') as $name => $label) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label]);
            $ids = [];
            foreach (config('summit.permissions.'.$name) as $permission) {
                $ids[] = Permission::firstOrCreate(['name' => $permission])->id;
            }
            $role->permissions()->sync($ids);
        }
    }
}
