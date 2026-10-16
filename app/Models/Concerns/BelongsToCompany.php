<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(AccessScope::class)->applyToCompanyRecords(
            $query,
            $user,
            $query->getModel()->getTable().'.company_id',
        );
    }
}
