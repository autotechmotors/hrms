@extends('layouts.app')
@section('title', 'Branch')
@section('content')
    <h1 class="h3 mb-3">{{ $branch ? 'Edit branch' : 'New branch' }}</h1>
    <form class="card card-body" method="post" action="{{ $branch ? route('branches.update', $branch) : route('branches.store') }}">
        @csrf @if($branch) @method('put') @endif
        <x-select-field name="company_id" label="Company" required>
            <option value="">Select</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected(old('company_id', $branch->company_id ?? '') == $company->id)>{{ $company->name }}</option>
            @endforeach
        </x-select-field>
        <x-text-field name="name" label="Name" :value="$branch->name ?? ''" required />
        <x-text-field name="code" label="Code" :value="$branch->code ?? ''" required />
        <x-text-field name="phone" label="Phone" :value="$branch->phone ?? ''" />
        <x-text-field name="email" label="Email" type="email" :value="$branch->email ?? ''" />
        <x-text-field name="address" label="Address" :value="$branch->address ?? ''" />
        <x-text-field name="city" label="City" :value="$branch->city ?? ''" />
        <x-text-field name="state" label="State" :value="$branch->state ?? ''" />
        <x-check-field name="is_active" label="Active" :checked="$branch->is_active ?? true" />
        <button class="btn btn-primary">Save</button>
    </form>
@endsection
