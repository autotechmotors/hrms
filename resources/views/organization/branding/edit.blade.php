@extends('layouts.app')
@section('title', 'Branding')
@section('content')
    <h1 class="h3 mb-3">Branding</h1>
    <form class="card card-body" method="post" action="{{ route('branding.update', $company) }}" enctype="multipart/form-data">
        @csrf @method('put')
        <x-text-field name="primary_color" label="Primary color" :value="$company->primary_color" required />
        <x-text-field name="logo" label="Logo" type="file" />
        <button class="btn btn-primary">Save branding</button>
    </form>
@endsection
