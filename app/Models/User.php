<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_assignments', 'user_id', 'role_id');
    }

    public function dashboardTabs(): HasMany
    {
        return $this->hasMany(DashboardTab::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function hasRole(string $role): bool
    {
        $this->loadMissing('roles.permissions');

        return $this->roles->contains('name', $role);
    }

    public function primaryRole(): ?Role
    {
        $this->loadMissing('roles');

        return $this->roles->first();
    }

    public function roleNames(): Collection
    {
        $this->loadMissing('roles');

        return $this->roles->pluck('name');
    }

    public function permissions(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->unique('id')
            ->values();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->contains('slug', $permission);
    }

    public function syncRoles(array $roles): void
    {
        $roleIds = Role::query()
            ->whereIn('id', $roles)
            ->orWhereIn('name', $roles)
            ->pluck('id')
            ->all();

        $this->roles()->sync($roleIds);
    }
}
