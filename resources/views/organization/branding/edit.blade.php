@extends('layouts.app')
@section('title', 'Branding')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Branding') }}</h1>
            <p>Company color and logo on printed and on-screen records.</p>
        </div>
    </div>
    <form class="card card-body org-form" method="post" action="{{ route('branding.update', $company) }}" enctype="multipart/form-data">
        @csrf @method('put')
        <x-text-field name="primary_color" label="Primary color" :value="$company->primary_color" required />
        <x-text-field name="logo" label="Logo" type="file" />
        <div class="org-form-actions">
            <button class="btn pr" type="submit">Save branding</button>
        </div>
    </form>
@endsection
