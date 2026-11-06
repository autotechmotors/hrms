<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'requires_mfa',
        'has_parent',
        'has_child',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'requires_mfa' => 'boolean',
            'has_parent' => 'boolean',
            'has_child' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')->withTimestamps();
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function requiresReportingManager(): bool
    {
        return $this->is_active && $this->has_parent;
    }

    public function appearsInManagerList(): bool
    {
        return $this->is_active && $this->has_child;
    }
}
