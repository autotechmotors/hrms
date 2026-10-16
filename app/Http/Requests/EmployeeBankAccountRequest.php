<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('bank_accounts.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'beneficiary_name' => ['required', 'string', 'max:160'],
            'bank_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'min:6', 'max:30'],
            'ifsc' => ['required', 'string', 'size:11'],
            'branch_name' => ['nullable', 'string', 'max:160'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
