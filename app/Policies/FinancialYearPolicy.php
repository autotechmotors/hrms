<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksModulePermission;

class FinancialYearPolicy
{
    use ChecksModulePermission;

    protected function module(): string
    {
        return 'financial_years';
    }
}
