<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CodedMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        [$table, $parameter] = match (true) {
            $this->routeIs('departments.*') => ['departments', 'department'],
            $this->routeIs('designations.*') => ['designations', 'designation'],
            $this->routeIs('employee-types.*') => ['employee_types', 'employee_type'],
            default => ['employment_statuses', 'employment_status'],
        };

        $record = $this->route($parameter);

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique($table, 'code')->where(fn ($query) => $query->where('company_id', $this->input('company_id')))->ignore($record?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_exit' => ['sometimes', 'boolean'],
            'employee_type_id' => $this->routeIs('designations.*')
                ? ['required', 'integer', 'exists:employee_types,id']
                : ['nullable'],
        ];
    }
}
