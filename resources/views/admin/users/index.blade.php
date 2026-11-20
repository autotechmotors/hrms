@extends('layouts.app')
@section('title', 'Users')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Users') }}</h1>
            <p>Sign-in accounts in your scope.</p>
        </div>
        <div class="ac">
            @permission('users.create')
                <a class="btn pr" href="{{ route('users.create') }}"><x-i name="plus" :size="14" />Add user</a>
            @endpermission
        </div>
    </div>
    <section class="card">
        <form class="tbar plain-bar" method="get">
            <input class="in" name="q" value="{{ request('q') }}" placeholder="Name or email" autocomplete="off">
        </form>
        <div data-live-list="users" data-page="{{ $users->currentPage() }}" data-last="{{ $users->lastPage() }}">
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
                <tbody data-list-rows>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->roles->pluck('name')->join(', ') ?: '—' }}</td>
                        <td><span class="b {{ $user->is_active ? 'ok' : 'er' }}">{{ $user->is_active ? 'Active' : 'Off' }}</span></td>
                        <td class="org-act">
                            @permission('users.edit')
                                <a class="btn sm" href="{{ route('users.edit', $user) }}">{{ __('Edit') }}</a>
                            @endpermission
                        </td>
                    </tr>
                @empty
                    <tr data-list-empty><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No users in your scope.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.list-footer', ['paginator' => $users])
        </div>
    </section>
@endsection
