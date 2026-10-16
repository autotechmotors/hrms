@extends('layouts.app')
@section('title', 'Locations')
@section('content')
    <div class="d-flex justify-content-between mb-3"><h1 class="h3">Locations</h1>@permission('locations.create')<a class="btn btn-primary" href="{{ route('locations.create') }}">Add</a>@endpermission</div>
    <form class="row g-2 mb-3" method="get"><div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search"></div><div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div></form>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Code</th><th>Name</th><th></th></tr></thead><tbody>
        @forelse($locations as $location)
            <tr><td>{{ $location->code }}</td><td>{{ $location->name }}</td>
                <td class="text-end">@permission('locations.edit')<a href="{{ route('locations.edit', $location) }}">Edit</a>@endpermission</td></tr>
        @empty<tr><td colspan="3" class="empty-state">No locations yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $locations->links() }}</div>
@endsection
