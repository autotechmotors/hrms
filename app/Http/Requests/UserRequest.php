<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        if ($target instanceof User) {
            return $this->user()?->can('update', $target) ?? false;
        }

        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'scopes' => ['nullable', 'array'],
            'scopes.*.scope_type' => ['nullable', Rule::in(['company', 'branch', 'department', 'employee'])],
            'scopes.*.scope_id' => ['nullable', 'integer'],
        ];
    }
}
