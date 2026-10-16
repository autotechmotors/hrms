@extends('layouts.app')
@section('title', 'Location')
@section('content')
    <h1 class="h3 mb-3">{{ $location ? 'Edit location' : 'New location' }}</h1>
    <form class="card card-body" method="post" action="{{ $location ? route('locations.update', $location) : route('locations.store') }}">
        @csrf @if($location) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $location->company_id ?? null, 'branchId' => $location->branch_id ?? null, 'withBranch' => true])
        <x-text-field name="name" label="Name" :value="$location->name ?? ''" required />
        <x-text-field name="code" label="Code" :value="$location->code ?? ''" required />
        <x-text-field name="address" label="Address" :value="$location->address ?? ''" />
        <x-check-field name="is_active" label="Active" :checked="$location->is_active ?? true" />
        <button class="btn btn-primary">Save</button>
    </form>
@endsection
