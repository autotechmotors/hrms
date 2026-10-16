<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Services\AccessScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AuditsChanges, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function hasRole(string $slug): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => $role->slug === $slug);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->contains(fn (Permission $permission) => $permission->slug === $slug);
    }

    public function requiresMfa(): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => $role->requires_mfa);
    }

    public function hasStaffLogin(): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $role) => $role->slug !== 'employee');
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        $companyIds = app(AccessScope::class)->companyIds($actor);

        return $query
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('slug', 'super-admin'))
            ->where(function (Builder $inner) use ($actor, $companyIds): void {
                $inner->where('users.id', $actor->id)
                    ->orWhereHas('employee', fn (Builder $employee) => $employee->whereIn('company_id', $companyIds))
                    ->orWhereHas('scopes', fn (Builder $scopes) => $scopes
                        ->where('scope_type', 'company')
                        ->whereIn('scope_id', $companyIds));
            });
    }
}
