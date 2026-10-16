@extends('layouts.app')
@section('title', 'Audit logs')
@section('content')
    <h1 class="h3 mb-3">Audit logs</h1>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-3"><input class="form-control" name="event" value="{{ request('event') }}" placeholder="Event"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
    </form>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>When</th><th>User</th><th>Event</th><th>Record</th><th>Changes</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                <td>{{ $log->user?->name ?? 'System' }}</td>
                <td>{{ $log->event }}</td>
                <td>{{ class_basename((string) $log->auditable_type) }} {{ $log->auditable_id }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit(json_encode($log->new_values), 120) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-state">No audit records for this filter.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
