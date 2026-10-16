<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class CompanyPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'companies';
    }
}
