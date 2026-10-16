@extends('layouts.app')
@section('title', $title)
@section('crumb', $title)
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ \Illuminate\Support\Str::plural($title) }}</h1>
        @permission($permission.'.create')
            <a class="btn btn-primary" href="{{ route($route.'.create') }}">Add</a>
        @endpermission
    </div>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search name or code"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
    </form>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th><th>Name</th>
                        @if($showCategory)<th>Category</th>@endif
                        @if($showExit)<th>Exit</th>@endif
                        <th>Active</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($records as $record)
                    <tr>
                        <td>{{ $record->code }}</td>
                        <td>{{ $record->name }}</td>
                        @if($showCategory)<td>{{ $record->employeeType?->name ?: 'Not set' }}</td>@endif
                        @if($showExit)<td>{{ $record->is_exit ? 'Yes' : 'No' }}</td>@endif
                        <td>{{ $record->is_active ? 'Yes' : 'No' }}</td>
                        <td class="text-end">
                            @permission($permission.'.edit')
                                <a href="{{ route($route.'.edit', $record) }}">Edit</a>
                            @endpermission
                            @permission($permission.'.delete')
                                <form class="d-inline" method="post" action="{{ route($route.'.destroy', $record) }}" data-confirm="Delete {{ $record->name }}?">
                                    @csrf @method('delete')
                                    <button class="btn btn-link text-danger p-0" type="submit">Delete</button>
                                </form>
                            @endpermission
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Nothing here yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $records->links() }}</div>
    @if($categoryLink)
        <p class="text-secondary mt-3 mb-0">Employment category belongs to the designation. <a href="{{ $categoryLink }}">Manage categories</a></p>
    @endif
@endsection
