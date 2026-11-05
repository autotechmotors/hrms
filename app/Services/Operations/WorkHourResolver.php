<?php

namespace App\Services\Operations;

use App\Models\DepartmentWorkHour;
use App\Models\EmployeeWorkHour;

class WorkHourResolver
{
    /**
     * @return array{hours: ?float, source: string, department_hours: ?float}
     */
    public function resolve(?DepartmentWorkHour $department, ?EmployeeWorkHour $employee): array
    {
        $departmentHours = $department?->hours_per_day !== null ? (float) $department->hours_per_day : null;

        if ($employee && ! $employee->uses_department && $employee->hours_per_day !== null) {
            return [
                'hours' => (float) $employee->hours_per_day,
                'source' => 'own',
                'department_hours' => $departmentHours,
            ];
        }

        if ($departmentHours !== null) {
            return [
                'hours' => $departmentHours,
                'source' => 'department',
                'department_hours' => $departmentHours,
            ];
        }

        return [
            'hours' => null,
            'source' => 'unset',
            'department_hours' => null,
        ];
    }

    public function label(?float $hours): string
    {
        if ($hours === null) {
            return 'Not set';
        }

        $text = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

        return $text.' h';
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'own' => 'Own hours',
            'department' => 'Department',
            default => 'Not set',
        };
    }
}
