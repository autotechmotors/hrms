@extends('layouts.app')
@section('title', 'Custom field')
@section('content')
    <h1 class="h3 mb-3">{{ $field ? 'Edit custom field' : 'New custom field' }}</h1>
    <form class="card card-body" method="post" action="{{ $field ? route('custom-fields.update', $field) : route('custom-fields.store') }}">
        @csrf @if($field) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $field->company_id ?? null])
        <x-text-field name="name" label="Name" :value="$field->name ?? ''" required />
        <x-text-field name="code" label="Code" :value="$field->code ?? ''" required />
        <x-select-field name="field_type" label="Type" required>
            @foreach(['text','number','date','textarea','select'] as $type)
                <option value="{{ $type }}" @selected(old('field_type', $field->field_type ?? 'text') === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </x-select-field>
        <x-text-field name="options" label="Select options, comma separated" :value="$field ? implode(', ', $field->options ?? []) : ''" />
        <x-text-field name="sort_order" label="Sort order" type="number" :value="$field->sort_order ?? 0" />
        <x-check-field name="is_required" label="Required" :checked="$field->is_required ?? false" />
        <x-check-field name="is_active" label="Active" :checked="$field->is_active ?? true" />
        <button class="btn btn-primary">Save</button>
    </form>
@endsection
