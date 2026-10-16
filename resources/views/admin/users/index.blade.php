@extends('layouts.app')
@section('title', 'Users')
@section('content')
    <div class="d-flex justify-content-between mb-3"><h1 class="h3">Users</h1>@permission('users.create')<a class="btn btn-primary" href="{{ route('users.create') }}">Add user</a>@endpermission</div>
    <form class="row g-2 mb-3" method="get"><div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Name or email"></div><div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div></form>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Active</th><th></th></tr></thead><tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->name }}</td><td>{{ $user->email }}</td>
                <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                <td>{{ $user->is_active ? 'Yes' : 'No' }}</td>
                <td class="text-end">@permission('users.edit')<a href="{{ route('users.edit', $user) }}">Edit</a>@endpermission</td>
            </tr>
        @empty<tr><td colspan="5" class="empty-state">No users in your scope.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $users->links() }}</div>
@endsection
