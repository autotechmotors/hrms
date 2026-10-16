@extends('layouts.app')
@section('title', $title)
@section('crumb', $title)
@section('content')
    <h1 class="h3 mb-3">{{ $title }}</h1>
    <form class="card card-body" method="post" action="{{ $action }}">
        @csrf
        @if($record) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $record->company_id ?? null])
        <x-text-field name="name" label="Name" :value="$record->name ?? ''" required />
        <x-text-field name="code" label="Code" :value="$record->code ?? ''" required />
        <x-text-field name="description" label="Description" type="textarea" :value="$record->description ?? ''" />
        @if($showCategory)
            <x-select-field name="employee_type_id" label="Employment category" required>
                <option value="">Select</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" data-company="{{ $category->company_id }}" @selected(old('employee_type_id', $record->employee_type_id ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select-field>
            <p class="small text-secondary">Permanent, contract, trainee, and similar. Employees inherit this from the designation. <a href="{{ route('employee-types.index') }}">Manage categories</a></p>
        @endif
        @if($showExit)
            <x-check-field name="is_exit" label="This status means the employee has exited" :checked="$record->is_exit ?? false" />
        @endif
        <x-check-field name="is_active" label="Active" :checked="$record->is_active ?? true" />
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-link" href="{{ url()->previous() }}">Cancel</a>
    </form>
@endsection
