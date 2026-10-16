<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'postal_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AccessScope::class)->applyToBranches($query, $user);
    }
}
