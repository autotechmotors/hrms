<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class Workspace
{
    /** @var array<int, Company|null> */
    private array $resolved = [];

    public function company(?User $user = null): ?Company
    {
        $key = (int) ($user?->getKey() ?? 0);
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        $query = Company::query()->orderBy('id');

        if ($user) {
            $query->visibleTo($user);
        }

        return $this->resolved[$key] = $query->first();
    }
}
