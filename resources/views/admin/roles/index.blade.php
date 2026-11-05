@extends('layouts.app')
@section('title', 'Roles')
@section('content')
    <div class="ph">
        <div>
            <h1>Roles</h1>
            <p>Parent / child flags decide Gate Pass and employee reporting rules.</p>
        </div>
        <div class="ac">
            @permission('roles.create')
                <a class="btn pr" href="{{ route('roles.create') }}">Add role</a>
            @endpermission
        </div>
    </div>

    <div class="card rl-list">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Has parent</th>
                        <th>Has child</th>
                        <th>Status</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td>
                                <b>{{ $role->name }}</b>
                                <small class="d-block text-secondary">{{ $role->slug }}</small>
                            </td>
                            <td><span class="rl-pill {{ $role->has_parent ? 'is-on' : '' }}">{{ $role->has_parent ? 'On' : 'Off' }}</span></td>
                            <td><span class="rl-pill {{ $role->has_child ? 'is-on' : '' }}">{{ $role->has_child ? 'On' : 'Off' }}</span></td>
                            <td><span class="rl-pill {{ $role->is_active ? 'is-live' : '' }}">{{ $role->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions_count }}</td>
                            <td class="text-end">
                                @permission('roles.edit')
                                    <a href="{{ route('roles.edit', $role) }}">Edit</a>
                                @endpermission
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">No roles yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rl-cards">
            @forelse($roles as $role)
                <article class="rl-block">
                    <header>
                        <div>
                            <b>{{ $role->name }}</b>
                            <small>{{ $role->slug }} · {{ $role->users_count }} users</small>
                        </div>
                        @permission('roles.edit')
                            <a class="btn sm" href="{{ route('roles.edit', $role) }}">Edit</a>
                        @endpermission
                    </header>
                    <div class="rl-meta">
                        <span>Parent <b class="{{ $role->has_parent ? 'on' : '' }}">{{ $role->has_parent ? 'On' : 'Off' }}</b></span>
                        <span>Child <b class="{{ $role->has_child ? 'on' : '' }}">{{ $role->has_child ? 'On' : 'Off' }}</b></span>
                        <span>Status <b class="{{ $role->is_active ? 'live' : '' }}">{{ $role->is_active ? 'Active' : 'Inactive' }}</b></span>
                    </div>
                </article>
            @empty
                <p class="empty-state">No roles yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-3">{{ $roles->links() }}</div>
@endsection
