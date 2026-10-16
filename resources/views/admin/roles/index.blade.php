@extends('layouts.app')
@section('title', 'Roles')
@section('content')
    <div class="d-flex justify-content-between mb-3"><h1 class="h3">Roles</h1>@permission('roles.create')<a class="btn btn-primary" href="{{ route('roles.create') }}">Add role</a>@endpermission</div>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Slug</th><th>Users</th><th>Permissions</th><th>Authenticator</th><th></th></tr></thead><tbody>
        @foreach($roles as $role)
            <tr>
                <td>{{ $role->name }}</td><td>{{ $role->slug }}</td><td>{{ $role->users_count }}</td><td>{{ $role->permissions_count }}</td>
                <td>{{ $role->requires_mfa ? 'Required' : 'No' }}</td>
                <td class="text-end">@permission('roles.edit')<a href="{{ route('roles.edit', $role) }}">Edit</a>@endpermission</td>
            </tr>
        @endforeach
    </tbody></table></div></div>
    <div class="mt-3">{{ $roles->links() }}</div>
@endsection
