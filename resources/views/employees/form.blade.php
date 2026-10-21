@extends('layouts.app')
@section('title', $employee ? 'Edit employee' : 'New employee')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ $employee ? 'Edit employee' : 'New employee' }}</h1>
            <p>{{ $employee ? $employee->full_name.' · '.$employee->employee_code : 'Add someone to your company roster' }}</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ $employee ? route('employees.show', $employee) : route('employees.index') }}">Cancel</a>
            <button class="btn pr" type="submit" form="employeeForm">{{ $employee ? 'Save changes' : 'Save employee' }}</button>
        </div>
    </div>

    <form id="employeeForm" class="ef" method="post" enctype="multipart/form-data" action="{{ $employee ? route('employees.update', $employee) : route('employees.store') }}">
        @csrf
        @if($employee) @method('put') @endif
        @include('partials.workspace-ids', ['companyId' => $employee->company_id ?? null, 'branchId' => $employee->branch_id ?? null, 'withBranch' => true])

        <div class="ef-grid">
            <div class="ef-main">
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Identity</h3>
                            <p>Name and how they appear on the roster</p>
                        </div>
                    </header>
                    <div class="cb">
                        @if($employee)
                            <div class="ef-code">
                                <span class="form-label">Employee code</span>
                                <strong>{{ $employee->employee_code }}</strong>
                                <small>Assigned once and cannot be changed</small>
                            </div>
                        @else
                            <p class="ef-hint">Employee code is assigned automatically from the company name (6 characters, e.g. AT0001 for Autotech).</p>
                        @endif
                        <div class="g c3">
                            <x-text-field name="first_name" label="First name" :value="$employee->first_name ?? ''" required />
                            <x-text-field name="middle_name" label="Middle name" :value="$employee->middle_name ?? ''" />
                            <x-text-field name="last_name" label="Last name" :value="$employee->last_name ?? ''" required />
                            <x-select-field name="gender" label="Gender">
                                <option value="">Select</option>
                                @foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('gender', $employee->gender ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-select-field>
                            <x-text-field name="date_of_birth" label="Date of birth" type="date" :value="optional($employee?->date_of_birth)->toDateString()" />
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Contact</h3>
                            <p>How you reach them at work</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c2">
                            <x-text-field name="email" label="Email" type="email" :value="$employee->email ?? ''" />
                            <x-text-field name="phone" label="Phone" :value="$employee->phone ?? ''" />
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Placement</h3>
                            <p>Where they sit in the organisation</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c3">
                            <x-select-field name="location_id" label="Location" data-depends-on="company">
                                <option value="">Select</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" data-company="{{ $location->company_id }}" @selected(old('location_id', $employee->location_id ?? '') == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </x-select-field>
                            <x-select-field name="department_id" label="Department" required data-depends-on="company">
                                <option value="">Select</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" data-company="{{ $department->company_id }}" @selected(old('department_id', $employee->department_id ?? '') == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </x-select-field>
                            <x-select-field name="designation_id" label="Designation" required data-depends-on="company">
                                <option value="">Select</option>
                                @foreach($designations as $designation)
                                    <option value="{{ $designation->id }}" data-company="{{ $designation->company_id }}" data-category="{{ $designation->employeeType?->name }}" @selected(old('designation_id', $employee->designation_id ?? '') == $designation->id)>{{ $designation->name }}</option>
                                @endforeach
                            </x-select-field>
                            <div class="mb-3">
                                <label class="form-label" for="designationCategory">Category</label>
                                <input class="form-control" id="designationCategory" value="{{ $employee?->employeeType?->name }}" readonly>
                                <div class="form-text">Inherited from the designation</div>
                            </div>
                            <x-select-field name="employment_status_id" label="Status" required data-depends-on="company">
                                <option value="">Select</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" data-company="{{ $status->company_id }}" @selected(old('employment_status_id', $employee->employment_status_id ?? '') == $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </x-select-field>
                            <x-select-field name="reporting_manager_id" label="Reporting manager" data-depends-on="company">
                                <option value="">None</option>
                                @foreach($managers as $manager)
                                    <option value="{{ $manager->id }}" data-company="{{ $manager->company_id }}" @selected(old('reporting_manager_id', $employee->reporting_manager_id ?? '') == $manager->id)>{{ $manager->full_name }} ({{ $manager->employee_code }})</option>
                                @endforeach
                            </x-select-field>
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>Employment terms</h3>
                            <p>Joining, probation, and notice</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c3">
                            <x-text-field name="joining_date" label="Joining date" type="date" :value="optional($employee?->joining_date)->toDateString()" required />
                            <x-text-field name="probation_days" label="Probation days" type="number" :value="$employee->probation_days ?? ''" />
                            <x-text-field name="notice_period_days" label="Notice period days" type="number" :value="$employee->notice_period_days ?? ''" />
                        </div>
                        @if($employee)
                            <div class="ef-note">
                                <div class="g c2">
                                    <x-text-field name="movement_reason" label="Reason if placement or status changes" />
                                    <x-text-field name="movement_effective_date" label="Movement effective date" type="date" :value="now()->toDateString()" />
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                @if($customFields->isNotEmpty())
                    <section class="card ef-sec">
                        <header>
                            <div>
                                <h3>Custom fields</h3>
                                <p>Extra details for this company</p>
                            </div>
                        </header>
                        <div class="cb">
                            <div class="g c2">
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
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            <aside class="ef-side">
                <section class="card ef-sec ef-photo">
                    <header>
                        <div>
                            <h3>Photograph</h3>
                            <p>Used on the attendance list and sent to the terminal</p>
                        </div>
                    </header>
                    <div class="cb">
                        @if($employee?->photograph_path)
                            <button type="button" class="att-zoom ef-thumb" data-photo="{{ route('employees.photo', $employee) }}" title="View photo">
                                <img src="{{ route('employees.photo', $employee) }}" alt="{{ $employee->full_name }}">
                            </button>
                        @else
                            <p class="ed-empty">No photo uploaded yet.</p>
                        @endif
                        <x-text-field name="photograph" label="Upload photo" type="file" accept="image/jpeg,image/png,image/webp" />
                        @if($employee && $terminals->isNotEmpty())
                            <p class="ef-hint">Save sends the name and this photo to {{ $terminals->pluck('name')->join(', ') }}.</p>
                        @elseif($employee)
                            <p class="ef-hint">Save sends the name and this photo to the company terminal.</p>
                        @endif
                    </div>
                </section>

                <section class="card ef-sec ef-help">
                    <header>
                        <div>
                            <h3>Before you save</h3>
                        </div>
                    </header>
                    <div class="cb">
                        <ul>
                            <li>Employee code is auto-generated and locked after save.</li>
                            <li>Category comes from the designation you pick.</li>
                            <li>Required fields are marked with a red star.</li>
                        </ul>
                    </div>
                </section>
            </aside>
        </div>

        <div id="attView" class="att-view" hidden>
            <button type="button" class="att-view-x btn" data-close>Close</button>
            <img id="attViewImg" alt="Employee photo">
        </div>

        <div class="ef-bar">
            <a class="btn" href="{{ $employee ? route('employees.show', $employee) : route('employees.index') }}">Cancel</a>
            <button class="btn pr" type="submit">{{ $employee ? 'Save changes' : 'Save employee' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function ($) {
    $(document).on('click', '#employeeForm [data-photo]', function (event) {
        event.preventDefault();
        const src = this.getAttribute('data-photo');
        if (!src) return;
        $('#attViewImg').attr('src', src);
        $('#attView').removeAttr('hidden');
    });
    $('#attView').on('click', function (event) {
        if (event.target === this || event.target.closest('[data-close]')) {
            this.hidden = true;
            $('#attViewImg').attr('src', '');
        }
    });
})(jQuery);
</script>
@endpush
