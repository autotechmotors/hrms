<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class EmploymentStatusPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'employment_statuses';
    }
}
