@extends('layouts.app')
@section('title', $company ? __('Edit company') : __('New company'))
@section('content')
    <div class="ph">
        <div>
            <x-back :href="route('companies.index')" label="Companies" />
            <h1>{{ $company ? __('Edit company') : __('New company') }}</h1>
        </div>
    </div>
    <form class="card card-body org-form" method="post" action="{{ $company ? route('companies.update', $company) : route('companies.store') }}" enctype="multipart/form-data">
        @csrf
        @if($company) @method('put') @endif
        <div class="row">
            <div class="col-md-6"><x-text-field name="name" label="Display name" :value="$company->name ?? ''" required /></div>
            <div class="col-md-6"><x-text-field name="legal_name" label="Legal name" :value="$company->legal_name ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="code" label="Code" :value="$company->code ?? ''" required /></div>
            <div class="col-md-4"><x-text-field name="email" label="Email" type="email" :value="$company->email ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="phone" label="Phone" :value="$company->phone ?? ''" /></div>
            <div class="col-md-6"><x-text-field name="website" label="Website" :value="$company->website ?? ''" /></div>
            <div class="col-md-6"><x-text-field name="primary_color" label="Brand color" :value="$company->primary_color ?? '#1d4ed8'" /></div>
            <div class="col-12"><x-text-field name="address" label="Address" :value="$company->address ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="city" label="City" :value="$company->city ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="state" label="State" :value="$company->state ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="postal_code" label="Postal code" :value="$company->postal_code ?? ''" /></div>
            <div class="col-md-6"><x-text-field name="gstin" label="GSTIN" :value="$company->gstin ?? ''" /></div>
            <div class="col-md-6"><x-text-field name="pan" label="PAN" :value="$company->pan ?? ''" /></div>
            <div class="col-md-6"><x-text-field name="logo" label="Logo" type="file" /></div>
        </div>
        <x-check-field name="is_active" label="Active" :checked="$company->is_active ?? true" />
        <div class="org-form-actions">
            <a class="btn" href="{{ route('companies.index') }}">{{ __('Cancel') }}</a>
            <button class="btn pr" type="submit">Save company</button>
        </div>
    </form>
@endsection
