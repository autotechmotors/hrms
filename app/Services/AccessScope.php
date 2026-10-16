<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessScope
{
    /**
     * Scopes are additive. A company scope grants that company.
     * A branch scope does not grant the rest of the company.
     * A manager also sees direct reports, without a company-wide grant.
     *
     * @return list<int>
     */
    public function companyIds(User $user): array
    {
        $user->loadMissing('scopes', 'employee');

        $ids = [];

        foreach ($user->scopes as $scope) {
            $ids[] = match ($scope->scope_type) {
                'company' => (int) $scope->scope_id,
                'branch' => (int) Branch::query()->whereKey($scope->scope_id)->value('company_id'),
                'department' => (int) Department::query()->whereKey($scope->scope_id)->value('company_id'),
                'employee' => (int) Employee::query()->whereKey($scope->scope_id)->value('company_id'),
                default => 0,
            };
        }

        if ($user->employee) {
            $ids[] = (int) $user->employee->company_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function canAccessCompany(User $user, int $companyId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($companyId, $this->companyIds($user), true);
    }

    public function applyToCompanies(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $ids = $this->companyIds($user);

        return $ids === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn($query->getModel()->getTable().'.id', $ids);
    }

    public function applyToCompanyRecords(Builder $query, User $user, string $column = 'company_id'): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $ids = $this->companyIds($user);

        return $ids === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn($column, $ids);
    }

    public function applyToBranches(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $query = $this->applyToCompanyRecords($query, $user, 'branches.company_id');
        $user->loadMissing('scopes');

        $companyScopes = $user->scopes->where('scope_type', 'company');
        $branchScopes = $user->scopes->where('scope_type', 'branch');

        if ($branchScopes->isNotEmpty() && $companyScopes->isEmpty()) {
            $query->whereIn('branches.id', $branchScopes->pluck('scope_id'));
        }

        return $query;
    }

    public function applyToDepartments(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $query = $this->applyToCompanyRecords($query, $user, 'departments.company_id');
        $user->loadMissing('scopes');

        $broader = $user->scopes->whereIn('scope_type', ['company', 'branch']);
        $departmentScopes = $user->scopes->where('scope_type', 'department');

        if ($departmentScopes->isNotEmpty() && $broader->isEmpty()) {
            $query->whereIn('departments.id', $departmentScopes->pluck('scope_id'));
        }

        return $query;
    }

    public function applyToEmployees(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $user->loadMissing('scopes', 'employee', 'roles');
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $outer) use ($user, $table): void {
            $applied = false;

            foreach ($user->scopes as $scope) {
                $applied = true;
                $outer->orWhere(function (Builder $inner) use ($scope, $table): void {
                    match ($scope->scope_type) {
                        'company' => $inner->where($table.'.company_id', $scope->scope_id),
                        'branch' => $inner->where($table.'.branch_id', $scope->scope_id),
                        'department' => $inner->where($table.'.department_id', $scope->scope_id),
                        'employee' => $inner->where($table.'.id', $scope->scope_id),
                        default => $inner->whereRaw('1 = 0'),
                    };
                });
            }

            if ($user->hasRole('manager') && $user->employee) {
                $applied = true;
                $outer->orWhere($table.'.reporting_manager_id', $user->employee->id);
            }

            if ($user->employee) {
                $applied = true;
                $outer->orWhere($table.'.id', $user->employee->id);
            }

            if (! $applied) {
                $outer->whereRaw('1 = 0');
            }
        });
    }

    public function canAccessEmployee(User $user, Employee $employee): bool
    {
        return Employee::query()->visibleTo($user)->whereKey($employee->id)->exists();
    }
}
