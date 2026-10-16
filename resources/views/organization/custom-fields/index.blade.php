@extends('layouts.app')
@section('title', 'Custom fields')
@section('content')
    <div class="d-flex justify-content-between mb-3"><h1 class="h3">Employee custom fields</h1>@permission('custom_fields.create')<a class="btn btn-primary" href="{{ route('custom-fields.create') }}">Add</a>@endpermission</div>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Required</th><th></th></tr></thead><tbody>
        @forelse($fields as $field)
            <tr>
                <td>{{ $field->code }}</td><td>{{ $field->name }}</td><td>{{ $field->field_type }}</td><td>{{ $field->is_required ? 'Yes' : 'No' }}</td>
                <td class="text-end">@permission('custom_fields.edit')<a href="{{ route('custom-fields.edit', $field) }}">Edit</a>@endpermission</td>
            </tr>
        @empty<tr><td colspan="5" class="empty-state">No custom fields yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $fields->links() }}</div>
@endsection
