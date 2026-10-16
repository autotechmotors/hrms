<?php

namespace App\Services\Employees;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeCustomFieldValue;
use App\Models\EmployeeType;
use App\Models\EmploymentEvent;
use App\Models\EmploymentStatus;
use App\Models\Location;
use App\Models\User;
use App\Services\AccessScope;
use App\Services\NumberSequenceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly AccessScope $access,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?UploadedFile $photo = null): Employee
    {
        return DB::transaction(function () use ($data, $actor, $photo): Employee {
            $this->assertPlacement($actor, $data, null);

            if (blank($data['employee_code'] ?? null)) {
                $company = \App\Models\Company::query()->findOrFail($data['company_id']);
                $data['employee_code'] = $this->sequences->nextEmployeeCode($company);
            }

            $data['employee_code'] = strtoupper((string) $data['employee_code']);
            $data = $this->nullBlanks($data);

            $employee = Employee::query()->create($data);
            $this->syncCustomFields($employee, $data['field_values'] ?? []);

            if ($photo) {
                $this->storePhoto($employee, $photo);
            }

            return $employee->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, User $actor, ?UploadedFile $photo = null): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor, $photo): Employee {
            $this->assertPlacement($actor, $data, $employee);
            $data['employee_code'] = strtoupper((string) ($data['employee_code'] ?? $employee->employee_code));
            $data = $this->nullBlanks($data);
            $fieldValues = $data['field_values'] ?? [];

            $placementChanged = $this->placementChanged($employee, $data);
            $statusChanged = (int) $employee->employment_status_id !== (int) $data['employment_status_id'];

            if (($placementChanged || $statusChanged) && blank($data['movement_reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'movement_reason' => 'A reason is required when branch, department, designation, manager, or status changes.',
                ]);
            }

            if ($placementChanged || $statusChanged) {
                EmploymentEvent::query()->create([
                    'employee_id' => $employee->id,
                    'event_type' => $placementChanged ? 'transfer' : 'status_change',
                    'effective_date' => $data['movement_effective_date'] ?? now()->toDateString(),
                    'reason' => $data['movement_reason'],
                    'from_branch_id' => $employee->branch_id,
                    'to_branch_id' => $data['branch_id'],
                    'from_department_id' => $employee->department_id,
                    'to_department_id' => $data['department_id'],
                    'from_designation_id' => $employee->designation_id,
                    'to_designation_id' => $data['designation_id'],
                    'from_manager_id' => $employee->reporting_manager_id,
                    'to_manager_id' => $data['reporting_manager_id'] ?? null,
                    'created_by' => $actor->id,
                ]);
            }

            unset($data['field_values'], $data['movement_reason'], $data['movement_effective_date']);
            $employee->update($data);
            $this->syncCustomFields($employee, $fieldValues);

            if ($photo) {
                $this->storePhoto($employee, $photo);
            } elseif (request()->boolean('remove_photo') && $employee->photograph_path) {
                Storage::disk('local')->delete($employee->photograph_path);
                $employee->update(['photograph_path' => null]);
            }

            return $employee->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPlacement(User $actor, array &$data, ?Employee $employee): void
    {
        $companyId = (int) $data['company_id'];

        if (! $this->access->canAccessCompany($actor, $companyId)) {
            throw ValidationException::withMessages(['company_id' => 'You cannot use this company.']);
        }

        $branch = Branch::query()->find($data['branch_id'] ?? null);
        $department = Department::query()->find($data['department_id'] ?? null);
        $designation = Designation::query()->find($data['designation_id'] ?? null);
        $type = EmployeeType::query()->find($data['employee_type_id'] ?? null);
        $status = EmploymentStatus::query()->find($data['employment_status_id'] ?? null);

        if (! $branch || (int) $branch->company_id !== $companyId) {
            throw ValidationException::withMessages(['branch_id' => 'Select a branch from the chosen company.']);
        }
        if (! $department || (int) $department->company_id !== $companyId) {
            throw ValidationException::withMessages(['department_id' => 'Select a department from the chosen company.']);
        }
        if (! $designation || (int) $designation->company_id !== $companyId) {
            throw ValidationException::withMessages(['designation_id' => 'Select a designation from the chosen company.']);
        }
        if ($designation->employee_type_id) {
            $data['employee_type_id'] = (int) $designation->employee_type_id;
            $type = EmployeeType::query()->find($data['employee_type_id']);
        }
        if (! $type || (int) $type->company_id !== $companyId) {
            throw ValidationException::withMessages(['designation_id' => 'Set an employment category on this designation first.']);
        }
        if (! $status || (int) $status->company_id !== $companyId) {
            throw ValidationException::withMessages(['employment_status_id' => 'Select a status from the chosen company.']);
        }

        if (! empty($data['location_id'])) {
            $location = Location::query()->find($data['location_id']);
            if (! $location || (int) $location->company_id !== $companyId || (int) $location->branch_id !== (int) $branch->id) {
                throw ValidationException::withMessages(['location_id' => 'Select a location from the chosen branch.']);
            }
        }

        if (! empty($data['reporting_manager_id'])) {
            $manager = Employee::query()->find($data['reporting_manager_id']);
            if (! $manager || (int) $manager->company_id !== $companyId) {
                throw ValidationException::withMessages(['reporting_manager_id' => 'Select a manager from the same company.']);
            }
            if ($employee && $this->createsManagerCycle($employee, $manager)) {
                throw ValidationException::withMessages(['reporting_manager_id' => 'That manager would create a reporting cycle.']);
            }
        }

        $actor->loadMissing('scopes');
        $companyScopes = $actor->scopes->where('scope_type', 'company');
        $branchScopes = $actor->scopes->where('scope_type', 'branch');
        $departmentScopes = $actor->scopes->where('scope_type', 'department');

        if (! $actor->isSuperAdmin() && $branchScopes->isNotEmpty() && $companyScopes->isEmpty()) {
            if (! $branchScopes->pluck('scope_id')->contains((int) $branch->id)) {
                throw ValidationException::withMessages(['branch_id' => 'You cannot assign this branch.']);
            }
        }

        if (! $actor->isSuperAdmin() && $departmentScopes->isNotEmpty() && $companyScopes->isEmpty() && $branchScopes->isEmpty()) {
            if (! $departmentScopes->pluck('scope_id')->contains((int) $department->id)) {
                throw ValidationException::withMessages(['department_id' => 'You cannot assign this department.']);
            }
        }

        if (! empty($data['email'])) {
            $exists = Employee::query()
                ->where('company_id', $companyId)
                ->where('email', $data['email'])
                ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['email' => 'Another employee in this company already uses this email.']);
            }
        }

        if (! empty($data['employee_code'])) {
            $exists = Employee::query()
                ->where('company_id', $companyId)
                ->where('employee_code', strtoupper((string) $data['employee_code']))
                ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['employee_code' => 'This employee code is already used in the company.']);
            }
        }
    }

    private function createsManagerCycle(Employee $employee, Employee $manager): bool
    {
        $cursor = $manager;
        $guard = 0;

        while ($cursor && $guard < 50) {
            if ((int) $cursor->id === (int) $employee->id) {
                return true;
            }
            $cursor = $cursor->reporting_manager_id
                ? Employee::query()->find($cursor->reporting_manager_id)
                : null;
            $guard++;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placementChanged(Employee $employee, array $data): bool
    {
        return (int) $employee->branch_id !== (int) $data['branch_id']
            || (int) $employee->department_id !== (int) $data['department_id']
            || (int) $employee->designation_id !== (int) $data['designation_id']
            || (int) ($employee->reporting_manager_id ?? 0) !== (int) ($data['reporting_manager_id'] ?? 0);
    }

    /**
     * @param  array<string|int, mixed>  $values
     */
    private function syncCustomFields(Employee $employee, array $values): void
    {
        $fields = EmployeeCustomField::query()
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->get();

        $errors = [];

        foreach ($fields as $field) {
            $value = $values[$field->id] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($field->is_required && ($value === null || $value === '')) {
                $errors['field_values.'.$field->id] = $field->name.' is required.';
                continue;
            }

            if ($field->field_type === 'select' && $value !== null && $value !== '' && ! in_array($value, $field->options ?? [], true)) {
                $errors['field_values.'.$field->id] = $field->name.' has an invalid option.';
                continue;
            }

            EmployeeCustomFieldValue::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'employee_custom_field_id' => $field->id],
                ['value' => $value === '' ? null : $value],
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function storePhoto(Employee $employee, UploadedFile $photo): void
    {
        if ($employee->photograph_path) {
            Storage::disk('local')->delete($employee->photograph_path);
        }

        $path = $photo->store('employee-photos/'.$employee->id, 'local');
        $employee->update(['photograph_path' => $path]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function nullBlanks(array $data): array
    {
        foreach (['email', 'phone', 'middle_name', 'location_id', 'reporting_manager_id', 'confirmation_date', 'date_of_birth', 'probation_days', 'notice_period_days'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }
}
