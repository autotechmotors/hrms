@extends('layouts.app')
@section('title', 'Custom fields')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Custom fields') }}</h1>
            <p>Extra fields stored on an employee.</p>
        </div>
        <div class="ac">
            @permission('custom_fields.create')
                <a class="btn pr" href="{{ route('custom-fields.create') }}"><x-i name="plus" :size="14" />Add field</a>
            @endpermission
        </div>
    </div>
    <section class="card">
        <div data-live-list="custom-fields" data-page="{{ $fields->currentPage() }}" data-last="{{ $fields->lastPage() }}">
        <div class="tw">
            <table>
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Required') }}</th><th></th></tr></thead>
                <tbody data-list-rows>
                @forelse($fields as $field)
                    <tr>
                        <td>{{ $field->code }}</td>
                        <td>{{ $field->name }}</td>
                        <td>{{ $field->field_type }}</td>
                        <td>{{ $field->is_required ? 'Yes' : 'No' }}</td>
                        <td class="org-act">
                            @permission('custom_fields.edit')
                                <a class="btn sm" href="{{ route('custom-fields.edit', $field) }}">{{ __('Edit') }}</a>
                            @endpermission
                        </td>
                    </tr>
                @empty
                    <tr data-list-empty><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No custom fields yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.list-footer', ['paginator' => $fields])
        </div>
    </section>
@endsection
