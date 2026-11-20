@extends('layouts.app')
@php
    $heading = $title;
    if (is_string($title) && str_starts_with($title, 'New ')) {
        $heading = __('New :item', ['item' => __(substr($title, 4))]);
    } elseif (is_string($title) && str_starts_with($title, 'Edit ')) {
        $heading = __('Edit :name', ['name' => substr($title, 5)]);
    }
@endphp
@section('title', $heading)
@section('crumb', $heading)
@section('content')
    <div class="ph">
        <div>
            <x-back :href="$listUrl" :label="__($listLabel)" />
            <h1>{{ $heading }}</h1>
        </div>
    </div>
    <form class="card card-body org-form" method="post" action="{{ $action }}">
        @csrf
        @if($record) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $record->company_id ?? null])
        <div class="org-form-grid">
            <x-text-field name="name" label="Name" :value="$record->name ?? ''" required />
            <x-text-field name="code" label="Code" :value="$record->code ?? ''" required />
        </div>
        <x-text-field name="description" label="Description" type="textarea" :value="$record->description ?? ''" />
        @if($showCategory)
            <x-select-field name="employee_type_id" label="Employment category" required>
                <option value="">Select</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" data-company="{{ $category->company_id }}" @selected(old('employee_type_id', $record->employee_type_id ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select-field>
            <p class="org-form-note">Permanent, contract, trainee, and similar. Employees inherit this from the designation. <a href="{{ route('employee-types.index') }}">Manage categories</a></p>
        @endif
        @if($showExit)
            <x-check-field name="is_exit" label="This status means the employee has exited" :checked="$record->is_exit ?? false" />
        @endif
        <x-check-field name="is_active" label="Active" :checked="$record->is_active ?? true" />
        <div class="org-form-actions">
            <a class="btn" href="{{ $listUrl }}">{{ __('Cancel') }}</a>
            <button class="btn pr" type="submit">{{ __('Save') }}</button>
        </div>
    </form>
@endsection
