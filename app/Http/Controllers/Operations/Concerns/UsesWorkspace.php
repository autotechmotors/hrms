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
            ->where('is_blocked', false)
            ->whereHas('employmentStatus', fn (Builder $query) => $query->where('is_exit', false))
            ->orderBy('first_name');
    }

    protected function authorizeAny(string ...$permissions): void
    {
        abort_unless(request()->user()->hasAnyPermission($permissions), 403);
    }

    protected function authorizePayroll(): void
    {
        $this->authorizeAny('payroll.process');
    }

    protected function authorizePayrollApprove(): void
    {
        $this->authorizeAny('payroll.approve');
    }

    protected function authorizeDevices(): void
    {
        $this->authorizeAny('devices.manage');
    }

    protected function authorizeBank(): void
    {
        $this->authorizeAny('bank_accounts.manage', 'payments.release');
    }
}
