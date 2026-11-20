@extends('layouts.app')
@section('title', 'Companies')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Companies') }}</h1>
            <p>Companies this login can open.</p>
        </div>
        <div class="ac">
            @permission('companies.create')
                <a class="btn pr" href="{{ route('companies.create') }}"><x-i name="plus" :size="14" />Add company</a>
            @endpermission
        </div>
    </div>
    <section class="card">
        <form class="tbar plain-bar" method="get">
            <input class="in" name="q" value="{{ request('q') }}" placeholder="Search" autocomplete="off">
        </form>
        <div data-live-list="companies" data-page="{{ $companies->currentPage() }}" data-last="{{ $companies->lastPage() }}">
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('City') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
                <tbody data-list-rows>
                @forelse($companies as $company)
                    <tr>
                        <td>{{ $company->code }}</td>
                        <td>{{ $company->name }}</td>
                        <td>{{ $company->city }}</td>
                        <td><span class="b {{ $company->is_active ? 'ok' : '' }}">{{ $company->is_active ? 'Active' : 'Off' }}</span></td>
                        <td class="org-act">
                            @permission('companies.edit')<a class="btn sm" href="{{ route('companies.edit', $company) }}">{{ __('Edit') }}</a>@endpermission
                            @permission('companies.delete')
                                <form method="post" action="{{ route('companies.destroy', $company) }}" data-confirm="{{ __('Delete :name?', ['name' => $company->name]) }}">
                                    @csrf @method('delete')
                                    <button class="btn sm org-del" type="submit">{{ __('Delete') }}</button>
                                </form>
                            @endpermission
                        </td>
                    </tr>
                @empty
                    <tr data-list-empty><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No companies yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.list-footer', ['paginator' => $companies])
        </div>
    </section>
@endsection
