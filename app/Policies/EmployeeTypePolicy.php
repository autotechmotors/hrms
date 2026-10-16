<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class EmployeeTypePolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'employee_types';
    }
}
