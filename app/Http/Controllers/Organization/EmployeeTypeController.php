<?php

namespace App\Http\Controllers\Organization;

use App\Models\EmployeeType;

class EmployeeTypeController extends CodedMasterController
{
    protected function model(): string
    {
        return EmployeeType::class;
    }

    protected function title(): string
    {
        return 'Employment category';
    }

    protected function routeName(): string
    {
        return 'employee-types';
    }

    protected function permission(): string
    {
        return 'employee_types';
    }

    protected function routeParameter(): string
    {
        return 'employee_type';
    }
}
