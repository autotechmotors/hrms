<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class EmployeeCustomFieldPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'custom_fields';
    }
}
