<?php

namespace App\Services;

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
}
