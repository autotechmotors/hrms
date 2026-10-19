<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'designation_id' => ['required', 'integer', 'exists:designations,id'],
            'employee_type_id' => ['nullable', 'integer', 'exists:employee_types,id'],
            'employment_status_id' => ['required', 'integer', 'exists:employment_statuses,id'],
            'reporting_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other'])],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'joining_date' => ['required', 'date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'probation_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'movement_reason' => ['nullable', 'string', 'max:255'],
            'movement_effective_date' => ['nullable', 'date'],
            'photograph' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
            'field_values' => ['nullable', 'array'],
            'field_values.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
