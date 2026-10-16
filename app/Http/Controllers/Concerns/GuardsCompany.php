<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use App\Services\AccessScope;
use Illuminate\Database\Eloquent\Collection;

trait GuardsCompany
{
    protected function guardCompany(int $companyId): void
    {
        abort_unless(app(AccessScope::class)->canAccessCompany(request()->user(), $companyId), 403);
    }

    protected function visibleCompanies(): Collection
    {
        return Company::query()->visibleTo(request()->user())->orderBy('name')->get();
    }
}
