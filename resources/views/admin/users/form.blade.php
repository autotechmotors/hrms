@extends('layouts.app')
@section('title', $account ? 'Edit user' : 'New user')
@section('content')
    <h1 class="h3 mb-3">{{ $account ? 'Edit user' : 'New user' }}</h1>
    <form class="card card-body" method="post" action="{{ $account ? route('users.update', $account) : route('users.store') }}">
        @csrf @if($account) @method('put') @endif
        <x-text-field name="name" label="Name" :value="$account->name ?? ''" required />
        <x-text-field name="email" label="Email" type="email" :value="$account->email ?? ''" required />
        <x-text-field name="password" label="Password" type="password" :required="!$account" />
        <x-text-field name="password_confirmation" label="Confirm password" type="password" :required="!$account" />
        <x-check-field name="is_active" label="Active" :checked="$account->is_active ?? true" />
        <div class="mb-3">
            <div class="form-label">Roles</div>
            <p class="small text-secondary">Only people with a staff role can sign in. Employees do not get a login unless an admin assigns a role such as Manager or HR. Authenticator stays on for roles that require it.</p>
            @foreach($roles as $role)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role{{ $role->id }}" @checked(in_array($role->id, old('roles', $account ? $account->roles->pluck('id')->all() : [])))>
                    <label class="form-check-label" for="role{{ $role->id }}">{{ $role->name }} @if($role->requires_mfa)<span class="text-secondary">(authenticator required)</span>@endif</label>
                </div>
            @endforeach
            @error('roles')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <div class="form-label">Data scope</div>
            <p class="small text-secondary">Scopes add access. A company scope includes that company. A branch scope does not include the rest of the company. Super Admin ignores scopes.</p>
            @for($i = 0; $i < 3; $i++)
                @php $existing = $account ? ($account->scopes[$i] ?? null) : null; @endphp
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <select class="form-select" name="scopes[{{ $i }}][scope_type]">
                            <option value="">None</option>
                            @foreach(['company' => 'Company', 'branch' => 'Branch', 'department' => 'Department', 'employee' => 'Employee'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('scopes.'.$i.'.scope_type', $existing->scope_type ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <input class="form-control" name="scopes[{{ $i }}][scope_id]" value="{{ old('scopes.'.$i.'.scope_id', $existing->scope_id ?? '') }}" placeholder="Record id">
                    </div>
                </div>
            @endfor
        </div>
        <button class="btn btn-primary">Save user</button>
    </form>
@endsection
