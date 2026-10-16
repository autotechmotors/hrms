<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class LocationPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'locations';
    }
}
