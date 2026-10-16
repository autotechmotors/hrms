@extends('layouts.app')
@section('title', 'Financial years')
@section('content')
    <div class="d-flex justify-content-between mb-3"><h1 class="h3">Financial years</h1>@permission('financial_years.create')<a class="btn btn-primary" href="{{ route('financial-years.create') }}">Add</a>@endpermission</div>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>From</th><th>To</th><th>Current</th><th></th></tr></thead><tbody>
        @forelse($years as $year)
            <tr>
                <td>{{ $year->name }}</td>
                <td>{{ $year->starts_on->format('d M Y') }}</td><td>{{ $year->ends_on->format('d M Y') }}</td>
                <td>{{ $year->is_current ? 'Yes' : 'No' }}</td>
                <td class="text-end">@permission('financial_years.edit')<a href="{{ route('financial-years.edit', $year) }}">Edit</a>@endpermission</td>
            </tr>
        @empty<tr><td colspan="5" class="empty-state">No financial years yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $years->links() }}</div>
@endsection
