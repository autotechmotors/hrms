<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'name',
        'legal_name',
        'code',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'postal_code',
        'gstin',
        'pan',
        'logo_path',
        'primary_color',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AccessScope::class)->applyToCompanies($query, $user);
    }
}
