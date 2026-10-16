<?php

namespace App\Services\Employees;

use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeExporter
{
    public function download(Builder $query): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'employee_code',
            'full_name',
            'email',
            'phone',
            'joining_date',
            'branch',
            'department',
            'designation',
            'employee_type',
            'employment_status',
            'reporting_manager',
        ], null, 'A1');

        $row = 2;
        $query->with(['branch', 'department', 'designation', 'employeeType', 'employmentStatus', 'manager'])
            ->orderBy('employee_code')
            ->chunk(200, function ($employees) use ($sheet, &$row): void {
                foreach ($employees as $employee) {
                    $sheet->fromArray([
                        $employee->employee_code,
                        $employee->full_name,
                        $employee->email,
                        $employee->phone,
                        optional($employee->joining_date)->toDateString(),
                        $employee->branch?->name,
                        $employee->department?->name,
                        $employee->designation?->name,
                        $employee->employeeType?->name,
                        $employee->employmentStatus?->name,
                        $employee->manager?->full_name,
                    ], null, 'A'.$row);
                    $row++;
                }
            });

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'employees.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
