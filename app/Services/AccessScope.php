<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessScope
{
    /** @var array<int, list<int>> */
    private array $companyIds = [];

    /**
     * Scopes are additive. A company scope grants that company.
     * A person on the reporting tree sees themself and every person below them,
     * at every level. A company scope does not add the rest of the company.
     *
     * @return list<int>
     */
    public function companyIds(User $user): array
    {
        $key = $user->getKey();
        if ($key && array_key_exists((int) $key, $this->companyIds)) {
            return $this->companyIds[(int) $key];
        }

        $user->loadMissing('scopes', 'employee');

        $ids = [];
        $departmentIds = [];
        $employeeIds = [];

        foreach ($user->scopes as $scope) {
            if ($scope->scope_type === 'company') {
                $ids[] = (int) $scope->scope_id;
            } elseif ($scope->scope_type === 'department') {
                $departmentIds[] = (int) $scope->scope_id;
            } elseif ($scope->scope_type === 'employee') {
                $employeeIds[] = (int) $scope->scope_id;
            }
        }

        if ($departmentIds !== []) {
            foreach (Department::query()->whereIn('id', $departmentIds)->pluck('company_id') as $companyId) {
                $ids[] = (int) $companyId;
            }
        }

        if ($employeeIds !== []) {
            foreach (Employee::query()->whereIn('id', $employeeIds)->pluck('company_id') as $companyId) {
                $ids[] = (int) $companyId;
            }
        }

        if ($user->employee) {
            $ids[] = (int) $user->employee->company_id;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($key) {
            $this->companyIds[(int) $key] = $ids;
        }

        return $ids;
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

    public function applyToDepartments(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $query = $this->applyToCompanyRecords($query, $user, 'departments.company_id');
        $user->loadMissing('scopes');

        $broader = $user->scopes->where('scope_type', 'company');
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

        if ($user->hasRole('hr-admin')) {
            return $this->applyToCompanyRecords($query, $user, $table.'.company_id');
        }

        $visibleIds = $user->employee
            ? $user->employee->familyMemberIds()->map(fn ($id) => (int) $id)->all()
            : [];

        if ($this->limitedToReportingTree($user)) {
            return $visibleIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn($table.'.id', $visibleIds);
        }

        return $query->where(function (Builder $outer) use ($user, $table, $visibleIds): void {
            $applied = false;

            foreach ($user->scopes as $scope) {
                $applied = true;
                $outer->orWhere(function (Builder $inner) use ($scope, $table): void {
                    match ($scope->scope_type) {
                        'company' => $inner->where($table.'.company_id', $scope->scope_id),
                        'department' => $inner->where($table.'.department_id', $scope->scope_id),
                        'employee' => $inner->where($table.'.id', $scope->scope_id),
                        default => $inner->whereRaw('1 = 0'),
                    };
                });
            }

            if ($visibleIds !== []) {
                $applied = true;
                $outer->orWhereIn($table.'.id', $visibleIds);
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

    /**
     * Director, Head, Manager, Employee, Helper, and any other role follow the
     * reporting tree once that login is linked to a person. People under a
     * manager are also visible to every parent above that manager.
     * Payroll, accounts, and device admin keep the company or department scope.
     */
    private function limitedToReportingTree(User $user): bool
    {
        if (! $user->employee) {
            return false;
        }

        foreach (['payroll-admin', 'accounts', 'device-administrator'] as $slug) {
            if ($user->hasRole($slug)) {
                return false;
            }
        }

        return true;
    }
}
