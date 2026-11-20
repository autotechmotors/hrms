@extends('layouts.app')
@section('title', 'Work calendars')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Work calendars') }}</h1>
            <p>Which weekdays count as working days.</p>
        </div>
        <div class="ac">
            @permission('work_calendars.create')
                <a class="btn pr" href="{{ route('work-calendars.create') }}"><x-i name="plus" :size="14" />Add calendar</a>
            @endpermission
        </div>
    </div>
    <section class="card">
        <div data-live-list="calendars" data-page="{{ $calendars->currentPage() }}" data-last="{{ $calendars->lastPage() }}">
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Effective') }}</th><th></th></tr></thead>
                <tbody data-list-rows>
                @forelse($calendars as $calendar)
                    <tr>
                        <td>{{ $calendar->name }}</td>
                        <td>{{ $calendar->effective_from->format('d M Y') }}</td>
                        <td class="org-act">
                            @permission('work_calendars.edit')
                                <a class="btn sm" href="{{ route('work-calendars.edit', $calendar) }}">{{ __('Edit') }}</a>
                            @endpermission
                        </td>
                    </tr>
                @empty
                    <tr data-list-empty><td colspan="3" style="text-align:center;color:var(--mu);padding:32px">No calendars yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.list-footer', ['paginator' => $calendars])
        </div>
    </section>
@endsection
