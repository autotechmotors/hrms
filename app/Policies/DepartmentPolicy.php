<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class DepartmentPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'departments';
    }
}
