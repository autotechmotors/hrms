<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employee_exits.create')
            || $this->user()?->hasPermission('employee_exits.edit')
            || false;
    }

    public function rules(): array
    {
        return [
            'resignation_date' => ['required', 'date'],
            'last_working_date' => ['required', 'date', 'after_or_equal:resignation_date'],
            'reason' => ['required', 'string', 'max:255'],
            'employment_status_id' => ['required', 'integer', 'exists:employment_statuses,id'],
            'fnf_status' => ['required', Rule::in(['not_started', 'pending', 'completed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
