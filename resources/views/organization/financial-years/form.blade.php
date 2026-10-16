@extends('layouts.app')
@section('title', 'Financial year')
@section('content')
    <h1 class="h3 mb-3">{{ $year ? 'Edit financial year' : 'New financial year' }}</h1>
    <form class="card card-body" method="post" action="{{ $year ? route('financial-years.update', $year) : route('financial-years.store') }}">
        @csrf @if($year) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $year->company_id ?? null])
        <x-text-field name="name" label="Name" :value="$year->name ?? ''" required />
        <x-text-field name="starts_on" label="Starts" type="date" :value="optional($year?->starts_on)->toDateString()" required />
        <x-text-field name="ends_on" label="Ends" type="date" :value="optional($year?->ends_on)->toDateString()" required />
        <x-check-field name="is_current" label="Current financial year" :checked="$year->is_current ?? false" />
        <button class="btn btn-primary">Save</button>
    </form>
@endsection
