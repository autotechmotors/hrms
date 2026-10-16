<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AccessScope::class)->applyToDepartments($query, $user);
    }
}
