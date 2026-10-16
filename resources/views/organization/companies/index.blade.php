@extends('layouts.app')
@section('title', 'Companies')
@section('crumb', 'Companies')
@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3">Companies</h1>
        @permission('companies.create')<a class="btn btn-primary" href="{{ route('companies.create') }}">Add company</a>@endpermission
    </div>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
    </form>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Code</th><th>Name</th><th>City</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @forelse($companies as $company)
            <tr>
                <td>{{ $company->code }}</td>
                <td>{{ $company->name }}</td>
                <td>{{ $company->city }}</td>
                <td>{{ $company->is_active ? 'Yes' : 'No' }}</td>
                <td class="text-end">
                    @permission('companies.edit')<a href="{{ route('companies.edit', $company) }}">Edit</a>@endpermission
                    @permission('companies.delete')
                        <form class="d-inline" method="post" action="{{ route('companies.destroy', $company) }}" data-confirm="Delete {{ $company->name }}?">
                            @csrf @method('delete')
                            <button class="btn btn-link text-danger p-0">Delete</button>
                        </form>
                    @endpermission
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-state">No companies yet.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $companies->links() }}</div>
@endsection
