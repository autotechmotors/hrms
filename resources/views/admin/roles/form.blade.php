@extends('layouts.app')
@section('title', $role ? 'Edit role' : 'New role')
@section('content')
    <h1 class="h3 mb-3">{{ $role ? 'Edit '.$role->name : 'New role' }}</h1>
    <form class="card card-body" method="post" action="{{ $role ? route('roles.update', $role) : route('roles.store') }}">
        @csrf @if($role) @method('put') @endif
        <x-text-field name="name" label="Name" :value="$role->name ?? ''" required />
        <x-text-field name="slug" label="Slug" :value="$role->slug ?? ''" required :readonly="$role?->is_system" />
        <x-text-field name="description" label="Description" :value="$role->description ?? ''" />
        <x-check-field name="requires_mfa" label="Require authenticator" :checked="$role->requires_mfa ?? false" />
        @if(($role->slug ?? '') !== 'super-admin')
            @php $selected = old('permissions', $role ? $role->permissions->pluck('id')->all() : []); @endphp
            @foreach($groups as $group => $permissions)
                <h2 class="h6 mt-3">{{ str_replace('_', ' ', $group) }}</h2>
                <div class="row">
                    @foreach($permissions as $permission)
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm{{ $permission->id }}" @checked(in_array($permission->id, $selected))>
                                <label class="form-check-label" for="perm{{ $permission->id }}">{{ $permission->name }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <p class="text-secondary">Super Admin always has every permission, including permissions added later.</p>
        @endif
        <button class="btn btn-primary mt-3">Save role</button>
    </form>
@endsection
