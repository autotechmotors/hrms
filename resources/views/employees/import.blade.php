@extends('layouts.app')
@section('title', 'Import employees')
@section('content')
    <h1 class="h3 mb-3">Import employees</h1>
    <p><a href="{{ route('employees.import.template') }}">Download the template</a>. Codes must already exist for department, designation, and status. Invalid rows stop the whole file.</p>
    <form class="card card-body" method="post" action="{{ route('employees.import.store') }}" enctype="multipart/form-data">
        @csrf
        @include('partials.workspace-ids')
        <x-text-field name="file" label="Spreadsheet" type="file" required />
        <button class="btn btn-primary">Import</button>
    </form>
@endsection
