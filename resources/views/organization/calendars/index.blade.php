@extends('layouts.app')
@section('title', 'Work calendars')
@section('content')
    <div class="d-flex justify-content-between mb-3"><h1 class="h3">Work calendars</h1>@permission('work_calendars.create')<a class="btn btn-primary" href="{{ route('work-calendars.create') }}">Add</a>@endpermission</div>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Effective</th><th></th></tr></thead><tbody>
        @forelse($calendars as $calendar)
            <tr>
                <td>{{ $calendar->name }}</td>
                <td>{{ $calendar->effective_from->format('d M Y') }}</td>
                <td class="text-end">@permission('work_calendars.edit')<a href="{{ route('work-calendars.edit', $calendar) }}">Edit</a>@endpermission</td>
            </tr>
        @empty<tr><td colspan="3" class="empty-state">No calendars yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $calendars->links() }}</div>
@endsection
