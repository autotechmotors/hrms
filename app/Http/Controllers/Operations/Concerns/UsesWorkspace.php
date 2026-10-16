<?php

namespace App\Http\Controllers\Operations\Concerns;

use App\Models\Employee;
use App\Services\Workspace;
use Illuminate\Database\Eloquent\Builder;

trait UsesWorkspace
{
    protected function companyId(): int
    {
        $company = app(Workspace::class)->company(request()->user());
        abort_unless($company, 404);

        return (int) $company->id;
    }

    protected function activeEmployees(): Builder
    {
        return Employee::query()
            ->visibleTo(request()->user())
            ->where('company_id', $this->companyId())
            ->whereHas('employmentStatus', fn (Builder $query) => $query->where('is_exit', false))
            ->orderBy('first_name');
    }

    protected function authorizeView(): void
    {
        $user = request()->user();
        abort_unless(
            $user->hasPermission('employees.view')
            || $user->hasPermission('payroll.process')
            || $user->hasPermission('devices.manage')
            || $user->hasPermission('bank_accounts.view'),
            403,
        );
    }

    protected function authorizePeople(): void
    {
        abort_unless(request()->user()->hasPermission('employees.edit'), 403);
    }

    protected function authorizePayroll(): void
    {
        $user = request()->user();
        abort_unless($user->hasPermission('payroll.process') || $user->hasPermission('employees.edit'), 403);
    }

    protected function authorizePayrollApprove(): void
    {
        $user = request()->user();
        abort_unless($user->hasPermission('payroll.approve') || $user->hasPermission('employees.edit'), 403);
    }

    protected function authorizeDevices(): void
    {
        $user = request()->user();
        abort_unless($user->hasPermission('devices.manage') || $user->hasPermission('employees.edit'), 403);
    }

    protected function authorizeBank(): void
    {
        $user = request()->user();
        abort_unless(
            $user->hasPermission('bank_accounts.manage')
            || $user->hasPermission('employees.edit')
            || $user->hasPermission('payments.release'),
            403,
        );
    }
}
