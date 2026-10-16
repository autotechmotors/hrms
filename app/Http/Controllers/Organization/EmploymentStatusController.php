<?php

namespace App\Http\Controllers\Organization;

use App\Models\EmploymentStatus;

class EmploymentStatusController extends CodedMasterController
{
    protected function model(): string
    {
        return EmploymentStatus::class;
    }

    protected function title(): string
    {
        return 'Employment status';
    }

    protected function routeName(): string
    {
        return 'employment-statuses';
    }

    protected function permission(): string
    {
        return 'employment_statuses';
    }

    protected function routeParameter(): string
    {
        return 'employment_status';
    }

    protected function hasExitFlag(): bool
    {
        return true;
    }
}
