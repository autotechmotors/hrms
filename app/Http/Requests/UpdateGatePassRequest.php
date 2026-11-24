<?php

namespace App\Http\Requests;

class UpdateGatePassRequest extends StoreGatePassRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('gate_passes.view') === true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['employee_id'], $rules['date']);

        return $rules;
    }
}
