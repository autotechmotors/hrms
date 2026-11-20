@extends('layouts.app')
@section('title', 'Financial years')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Financial years') }}</h1>
            <p>The year a payroll period belongs to.</p>
        </div>
        <div class="ac">
            @permission('financial_years.create')
                <a class="btn pr" href="{{ route('financial-years.create') }}"><x-i name="plus" :size="14" />Add year</a>
            @endpermission
        </div>
    </div>
    <section class="card">
        <div data-live-list="financial-years" data-page="{{ $years->currentPage() }}" data-last="{{ $years->lastPage() }}">
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Current') }}</th><th></th></tr></thead>
                <tbody data-list-rows>
                @forelse($years as $year)
                    <tr>
                        <td>{{ $year->name }}</td>
                        <td>{{ $year->starts_on->format('d M Y') }}</td>
                        <td>{{ $year->ends_on->format('d M Y') }}</td>
                        <td>@if($year->is_current)<span class="b ok">Current</span>@else<span class="b">No</span>@endif</td>
                        <td class="org-act">
                            @permission('financial_years.edit')
                                <a class="btn sm" href="{{ route('financial-years.edit', $year) }}">{{ __('Edit') }}</a>
                            @endpermission
                        </td>
                    </tr>
                @empty
                    <tr data-list-empty><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No financial years yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.list-footer', ['paginator' => $years])
        </div>
    </section>
@endsection
