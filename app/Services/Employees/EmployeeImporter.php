<?php

namespace App\Services\Employees;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EmployeeImporter
{
    public const HEADERS = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'joining_date',
        'branch_code',
        'department_code',
        'designation_code',
        'employee_type_code',
        'employment_status_code',
        'reporting_manager_code',
    ];

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly AccessScope $access,
    ) {
    }

    /**
     * @return array{created: int, errors: list<string>}
     */
    public function import(UploadedFile $file, int $companyId, User $actor): array
    {
        if (! $this->access->canAccessCompany($actor, $companyId)) {
            return ['created' => 0, 'errors' => ['You cannot import into this company.']];
        }

        $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);
        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $rows[0] ?? []);

        foreach (self::HEADERS as $expected) {
            if (! in_array($expected, $header, true)) {
                return ['created' => 0, 'errors' => ['The file is missing the '.$expected.' column.']];
            }
        }

        $indexes = array_flip($header);
        $payloads = [];
        $errors = [];

        foreach (array_slice($rows, 1, 500) as $offset => $row) {
            $line = $offset + 2;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $read = fn (string $column) => trim((string) ($row[$indexes[$column]] ?? ''));
            $joining = $this->normalizeDate($row[$indexes['joining_date']] ?? null);

            $branch = Branch::query()->where('company_id', $companyId)->where('code', strtoupper($read('branch_code')))->first();
            $department = Department::query()->where('company_id', $companyId)->where('code', strtoupper($read('department_code')))->first();
            $designation = Designation::query()->where('company_id', $companyId)->where('code', strtoupper($read('designation_code')))->first();
            $typeFromSheet = $read('employee_type_code') === ''
                ? null
                : EmployeeType::query()->where('company_id', $companyId)->where('code', strtoupper($read('employee_type_code')))->first();
            $type = $designation?->employee_type_id
                ? EmployeeType::query()->find($designation->employee_type_id)
                : $typeFromSheet;
            $status = EmploymentStatus::query()->where('company_id', $companyId)->where('code', strtoupper($read('employment_status_code')))->first();

            $rowErrors = [];
            if ($read('first_name') === '' || $read('last_name') === '') {
                $rowErrors[] = 'first and last name are required';
            }
            if (! $branch) {
                $rowErrors[] = 'branch code was not found';
            }
            if (! $department) {
                $rowErrors[] = 'department code was not found';
            }
            if (! $designation) {
                $rowErrors[] = 'designation code was not found';
            }
            if ($designation?->employee_type_id && $read('employee_type_code') !== '' && (! $typeFromSheet || (int) $typeFromSheet->id !== (int) $designation->employee_type_id)) {
                $rowErrors[] = 'employee type code does not match the designation category';
            }
            if (! $type) {
                $rowErrors[] = 'designation has no employment category';
            }
            if (! $status) {
                $rowErrors[] = 'employment status code was not found';
            }
            if ($joining === null) {
                $rowErrors[] = 'joining date must be YYYY-MM-DD';
            }

            $managerId = null;
            if ($read('reporting_manager_code') !== '') {
                $manager = Employee::query()
                    ->where('company_id', $companyId)
                    ->where('employee_code', strtoupper($read('reporting_manager_code')))
                    ->first();
                if (! $manager) {
                    $rowErrors[] = 'reporting manager code was not found';
                } else {
                    $managerId = $manager->id;
                }
            }

            if ($rowErrors !== []) {
                $errors[] = 'Row '.$line.': '.implode(', ', $rowErrors).'.';
                continue;
            }

            $payloads[] = [
                'company_id' => $companyId,
                'employee_code' => strtoupper($read('employee_code')),
                'first_name' => $read('first_name'),
                'last_name' => $read('last_name'),
                'email' => $read('email'),
                'phone' => $read('phone'),
                'joining_date' => $joining,
                'branch_id' => $branch->id,
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'employee_type_id' => $type->id,
                'employment_status_id' => $status->id,
                'reporting_manager_id' => $managerId,
            ];
        }

        if ($payloads === [] && $errors === []) {
            return ['created' => 0, 'errors' => ['The file has no employee rows.']];
        }

        if ($errors !== []) {
            return ['created' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($payloads, $actor): void {
            foreach ($payloads as $payload) {
                $this->employees->create($payload, $actor);
            }
        });

        return ['created' => count($payloads), 'errors' => []];
    }

    public function template(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $path = storage_path('app/private/employee-import-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }
}
