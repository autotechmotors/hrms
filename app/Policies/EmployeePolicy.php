<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Services\AccessScope;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.view')
            && app(AccessScope::class)->canAccessEmployee($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.create');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.edit')
            && app(AccessScope::class)->canAccessEmployee($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.delete')
            && app(AccessScope::class)->canAccessEmployee($user, $employee);
    }
}
