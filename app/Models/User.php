<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $attributes = ['is_active' => true];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('name', $role);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && ($this->hasRole('super_admin')
            || $this->permissions()->where('name', $permission)->exists()
            || $this->roles()->whereHas('permissions', fn ($query) => $query->where('name', $permission))->exists());
    }

    public function workspace(): string
    {
        foreach (['super_admin' => 'admin', 'info_com' => 'info', 'follow_up_admin' => 'follow-up', 'data_entry' => 'guest-entry', 'officer' => 'my-guests'] as $role => $route) {
            if ($this->hasRole($role)) {
                return $route;
            }
        }

        return 'access-denied';
    }

    public function roleLabel(): string
    {
        return $this->roles->pluck('label')->implode(' · ') ?: 'No role assigned';
    }
}
