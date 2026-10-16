<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('employee_custom_fields', 'code')
                    ->where(fn ($query) => $query->where('company_id', $this->input('company_id')))
                    ->ignore($this->route('custom_field')?->id),
            ],
            'field_type' => ['required', Rule::in(['text', 'number', 'date', 'textarea', 'select'])],
            'options' => ['nullable', 'string', 'max:1000', 'required_if:field_type,select'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
