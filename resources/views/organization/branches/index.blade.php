@extends('layouts.app')
@section('title', 'Branches')
@section('content')
    <div class="d-flex justify-content-between mb-3"><h1 class="h3">Branches</h1>@permission('branches.create')<a class="btn btn-primary" href="{{ route('branches.create') }}">Add</a>@endpermission</div>
    <form class="row g-2 mb-3" method="get"><div class="col-md-4"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search"></div><div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div></form>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Code</th><th>Name</th><th>Company</th><th></th></tr></thead><tbody>
        @forelse($branches as $branch)
            <tr>
                <td>{{ $branch->code }}</td><td>{{ $branch->name }}</td><td>{{ $branch->company?->name }}</td>
                <td class="text-end">@permission('branches.edit')<a href="{{ route('branches.edit', $branch) }}">Edit</a>@endpermission
                    @permission('branches.delete')<form class="d-inline" method="post" action="{{ route('branches.destroy', $branch) }}" data-confirm="Delete this branch?"><button class="btn btn-link text-danger p-0">Delete</button>@csrf @method('delete')</form>@endpermission</td>
            </tr>
        @empty<tr><td colspan="4" class="empty-state">No branches yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $branches->links() }}</div>
@endsection
