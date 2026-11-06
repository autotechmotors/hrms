@extends('layouts.app')
@section('title', 'Roles')
@section('content')
    <div class="rl-board">
        <div class="ph">
            <div>
                <h1>Roles</h1>
                <p>{{ $stats['total'] }} roles · parent / child flags drive employee reporting and Gate Pass</p>
            </div>
            <div class="ac">
                @permission('roles.create')
                    <a class="btn pr" href="{{ route('roles.create') }}"><x-i name="plus" :size="14" />Add role</a>
                @endpermission
            </div>
        </div>

        <div class="lv-stats g c4">
            <div class="card kp">
                <small>Roles</small>
                <b>{{ $stats['total'] }}</b>
                <span>In this company</span>
            </div>
            <div class="card kp">
                <small>Active</small>
                <b>{{ $stats['active'] }}</b>
                <span>Shown on employee form</span>
            </div>
            <div class="card kp">
                <small>Has parent</small>
                <b>{{ $stats['parent'] }}</b>
                <span>Parent select required</span>
            </div>
            <div class="card kp">
                <small>Has child</small>
                <b>{{ $stats['child'] }}</b>
                <span>Appear in parent list</span>
            </div>
        </div>

        <div class="rl-grid">
            @if($roles->isEmpty())
                <div class="card cb empty-state rl-empty">
                    <h3>No roles yet</h3>
                    <p>Add a role to set parent / child rules and permissions.</p>
                </div>
            @endif
            @foreach ($roles as $role)
                <article class="rl-card {{ $role->is_active ? '' : 'is-off' }}">
                    <a class="rl-card-main" href="{{ $canEdit ? route('roles.edit', $role) : route('roles.index') }}">
                        <span class="rl-ico"><x-i name="shield" :size="18" /></span>
                        <span class="rl-copy">
                            <span class="rl-title">
                                <b>{{ $role->name }}</b>
                                <span class="b {{ $role->is_active ? 'ok' : '' }}">{{ $role->is_active ? 'Active' : 'Inactive' }}</span>
                            </span>
                            <small>{{ $role->slug }}{{ $role->is_system ? ' · System' : '' }}</small>
                            @if($role->description)
                                <span>{{ $role->description }}</span>
                            @endif
                        </span>
                    </a>
                    <div class="rl-facts">
                        <div>
                            <dt>Parent</dt>
                            <dd class="{{ $role->has_parent ? 'is-on' : '' }}">{{ $role->has_parent ? 'On' : 'Off' }}</dd>
                        </div>
                        <div>
                            <dt>Child</dt>
                            <dd class="{{ $role->has_child ? 'is-on' : '' }}">{{ $role->has_child ? 'On' : 'Off' }}</dd>
                        </div>
                        <div>
                            <dt>MFA</dt>
                            <dd class="{{ $role->requires_mfa ? 'is-on' : '' }}">{{ $role->requires_mfa ? 'On' : 'Off' }}</dd>
                        </div>
                        <div>
                            <dt>People</dt>
                            <dd>{{ $role->employees_count }}</dd>
                        </div>
                    </div>
                    <footer class="rl-foot">
                        <span>{{ $role->permissions_count }} permissions</span>
                        @if($canEdit)
                            <a class="btn sm" href="{{ route('roles.edit', $role) }}">Edit</a>
                        @endif
                    </footer>
                </article>
            @endforeach
        </div>

        <div class="pgn rl-pgn">{{ $roles->links() }}</div>
    </div>
@endsection
