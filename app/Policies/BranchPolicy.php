<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class BranchPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'branches';
    }
}
