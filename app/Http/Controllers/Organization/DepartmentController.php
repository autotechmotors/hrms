<?php

namespace App\Http\Controllers\Organization;

use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;

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

    protected function summary(): string
    {
        return 'Departments used when placing employees.';
    }

    protected function showsPeople(): bool
    {
        return true;
    }

    protected function prepareIndex(Builder $query): void
    {
        $query->withCount('employees');
    }
}
