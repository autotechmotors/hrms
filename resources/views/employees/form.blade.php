@extends('layouts.app')
@section('title', $employee ? 'Edit employee' : 'New employee')
@section('content')
    <h1 class="h3 mb-3">{{ $employee ? 'Edit '.$employee->full_name : 'New employee' }}</h1>
    <form class="card card-body" method="post" enctype="multipart/form-data" action="{{ $employee ? route('employees.update', $employee) : route('employees.store') }}">
        @csrf @if($employee) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $employee->company_id ?? null, 'branchId' => $employee->branch_id ?? null, 'withBranch' => true])
        <div class="row">
            <div class="col-md-4"><x-text-field name="employee_code" label="Employee code" :value="$employee->employee_code ?? ''" placeholder="Leave blank to generate" /></div>
            <div class="col-md-4"><x-text-field name="joining_date" label="Joining date" type="date" :value="optional($employee?->joining_date)->toDateString()" required /></div>
            <div class="col-md-4"><x-text-field name="first_name" label="First name" :value="$employee->first_name ?? ''" required /></div>
            <div class="col-md-4"><x-text-field name="middle_name" label="Middle name" :value="$employee->middle_name ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="last_name" label="Last name" :value="$employee->last_name ?? ''" required /></div>
            <div class="col-md-4"><x-text-field name="email" label="Email" type="email" :value="$employee->email ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="phone" label="Phone" :value="$employee->phone ?? ''" /></div>
            <div class="col-md-4">
                <x-select-field name="gender" label="Gender">
                    <option value="">Select</option>
                    @foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $employee->gender ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </x-select-field>
            </div>
            <div class="col-md-4">
                <x-select-field name="location_id" label="Location" data-depends-on="company">
                    <option value="">Select</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" data-company="{{ $location->company_id }}" @selected(old('location_id', $employee->location_id ?? '') == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </x-select-field>
            </div>
            <div class="col-md-4">
                <x-select-field name="department_id" label="Department" required data-depends-on="company">
                    <option value="">Select</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" data-company="{{ $department->company_id }}" @selected(old('department_id', $employee->department_id ?? '') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </x-select-field>
            </div>
            <div class="col-md-4">
                <x-select-field name="designation_id" label="Designation" required data-depends-on="company">
                    <option value="">Select</option>
                    @foreach($designations as $designation)
                        <option value="{{ $designation->id }}" data-company="{{ $designation->company_id }}" data-category="{{ $designation->employeeType?->name }}" @selected(old('designation_id', $employee->designation_id ?? '') == $designation->id)>{{ $designation->name }}</option>
                    @endforeach
                </x-select-field>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="designationCategory">Category</label>
                <input class="form-control" id="designationCategory" value="{{ $employee?->employeeType?->name }}" readonly>
                <div class="form-text">Permanent, contract, or trainee. Set this on the designation.</div>
            </div>
            <div class="col-md-4">
                <x-select-field name="employment_status_id" label="Status" required data-depends-on="company">
                    <option value="">Select</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" data-company="{{ $status->company_id }}" @selected(old('employment_status_id', $employee->employment_status_id ?? '') == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </x-select-field>
            </div>
            <div class="col-md-4">
                <x-select-field name="reporting_manager_id" label="Reporting manager" data-depends-on="company">
                    <option value="">None</option>
                    @foreach($managers as $manager)
                        <option value="{{ $manager->id }}" data-company="{{ $manager->company_id }}" @selected(old('reporting_manager_id', $employee->reporting_manager_id ?? '') == $manager->id)>{{ $manager->full_name }} ({{ $manager->employee_code }})</option>
                    @endforeach
                </x-select-field>
            </div>
            <div class="col-md-4"><x-text-field name="probation_days" label="Probation days" type="number" :value="$employee->probation_days ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="notice_period_days" label="Notice period days" type="number" :value="$employee->notice_period_days ?? ''" /></div>
            <div class="col-md-4"><x-text-field name="date_of_birth" label="Date of birth" type="date" :value="optional($employee?->date_of_birth)->toDateString()" /></div>
            <div class="col-md-6"><x-text-field name="photograph" label="Photograph" type="file" /></div>
            @if($employee)
                <div class="col-md-6"><x-text-field name="movement_reason" label="Reason if placement or status changes" /></div>
                <div class="col-md-6"><x-text-field name="movement_effective_date" label="Movement effective date" type="date" :value="now()->toDateString()" /></div>
            @endif
        </div>
        @foreach($customFields as $field)
            @php $current = optional($employee?->customValues?->firstWhere('employee_custom_field_id', $field->id))->value; @endphp
            <div data-company-panel="{{ $field->company_id }}">
                @if($field->field_type === 'select')
                    <x-select-field name="field_values[{{ $field->id }}]" :label="$field->name" :required="$field->is_required">
                        <option value="">Select</option>
                        @foreach($field->options ?? [] as $option)
                            <option value="{{ $option }}" @selected(old('field_values.'.$field->id, $current) === $option)>{{ $option }}</option>
                        @endforeach
                    </x-select-field>
                @else
                    <x-text-field name="field_values[{{ $field->id }}]" :label="$field->name" :type="$field->field_type === 'textarea' ? 'textarea' : ($field->field_type === 'number' ? 'number' : ($field->field_type === 'date' ? 'date' : 'text'))" :value="$current" :required="$field->is_required" />
                @endif
            </div>
        @endforeach
        <button class="btn btn-primary" type="submit">Save employee</button>
    </form>
@endsection
