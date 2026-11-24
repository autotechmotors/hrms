<?php

namespace App\Models\Concerns;

use App\Models\Employee;
use App\Models\GatePass;

trait DecidesGatePasses
{
    public function isGatePassAdmin(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRole('hr-admin')
            || $this->hasPermission('gate_passes.manage');
    }

    public function canDecideGatePassFor(Employee $employee): bool
    {
        $this->loadMissing('employee');

        if ($this->employee && (int) $this->employee->id === (int) $employee->id) {
            return false;
        }

        return $this->isGatePassAdmin() || $this->employee?->manages($employee) === true;
    }

    public function canEditGatePass(GatePass $pass): bool
    {
        if (! $pass->isPending() || $this->isParentOfGatePass($pass)) {
            return false;
        }

        $this->loadMissing('employee');

        return ($this->employee && (int) $this->employee->id === (int) $pass->employee_id)
            || $this->isGatePassAdmin();
    }

    public function canDeleteGatePass(GatePass $pass): bool
    {
        if ($this->isParentOfGatePass($pass)) {
            return false;
        }

        $this->loadMissing('employee');
        if ($this->employee && (int) $this->employee->id === (int) $pass->employee_id) {
            return $pass->isPending();
        }

        return $this->isGatePassAdmin();
    }

    protected function isParentOfGatePass(GatePass $pass): bool
    {
        $this->loadMissing('employee');
        $pass->loadMissing('employee');
        $employee = $pass->employee;

        if (! $this->employee || ! $employee || (int) $this->employee->id === (int) $employee->id) {
            return false;
        }

        return $this->employee->manages($employee);
    }
}
