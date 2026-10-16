<?php

namespace App\Http\Controllers\Organization;

use App\Models\Department;

class DepartmentController extends CodedMasterController
{
    protected function model(): string
    {
        return Department::class;
    }

    protected function title(): string
    {
        return 'Department';
    }

    protected function routeName(): string
    {
        return 'departments';
    }

    protected function permission(): string
    {
        return 'departments';
    }

    protected function routeParameter(): string
    {
        return 'department';
    }
}
