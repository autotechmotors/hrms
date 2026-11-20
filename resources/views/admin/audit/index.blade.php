@extends('layouts.app')
@section('title', 'Audit logs')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Audit logs') }}</h1>
            <p>Changes recorded for records you can see.</p>
        </div>
    </div>
    <section class="card">
        <form class="tbar plain-bar" method="get">
            <input class="in" name="event" value="{{ request('event') }}" placeholder="Event">
            <input class="in" type="date" name="from" value="{{ request('from') }}" aria-label="From">
            <input class="in" type="date" name="to" value="{{ request('to') }}" aria-label="To">
            <button class="btn" type="submit">{{ __('Filter') }}</button>
        </form>
        <div data-live-list="audit" data-page="{{ $logs->currentPage() }}" data-last="{{ $logs->lastPage() }}">
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Event') }}</th><th>{{ __('Record') }}</th><th>{{ __('Changes') }}</th></tr></thead>
                <tbody data-list-rows>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ \App\Support\Clock::dateTime($log->created_at) }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td>{{ $log->event }}</td>
                        <td>{{ class_basename((string) $log->auditable_type) }} {{ $log->auditable_id }}</td>
                        <td>{{ \Illuminate\Support\Str::limit(json_encode($log->new_values), 120) }}</td>
                    </tr>
                @empty
                    <tr data-list-empty><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No audit records for this filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.list-footer', ['paginator' => $logs])
        </div>
    </section>
@endsection
