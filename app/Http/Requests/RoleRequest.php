<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role
            ? ($this->user()?->can('update', $role) ?? false)
            : ($this->user()?->can('create', Role::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_parent' => $this->boolean('has_parent'),
            'has_child' => $this->boolean('has_child'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'requires_mfa' => $this->boolean('requires_mfa'),
        ]);
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('roles', 'slug')->ignore($roleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'has_parent' => ['required', 'boolean'],
            'has_child' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'requires_mfa' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
