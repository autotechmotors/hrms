<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;

class Workspace
{
    public function company(?User $user = null): ?Company
    {
        $query = Company::query()->orderBy('id');

        if ($user) {
            $query->visibleTo($user);
        }

        return $query->first();
    }

    public function branch(?User $user = null): ?Branch
    {
        $company = $this->company($user);

        if (! $company) {
            return null;
        }

        return Branch::query()->where('company_id', $company->id)->orderBy('id')->first();
    }
}
