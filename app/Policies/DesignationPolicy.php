<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class DesignationPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'designations';
    }
}
