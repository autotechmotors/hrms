<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class WorkCalendarPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'work_calendars';
    }
}
