<?php

namespace App\Models\Concerns;

use App\Models\Employee;

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
}
