@extends('layouts.app')
@section('title', $employee ? __('Edit employee') : __('New employee'))
@section('content')
    <div class="ph">
        <div>
            <x-back :href="route('employees.index')" label="Employees" />
            <h1>{{ $employee ? __('Edit employee') : __('New employee') }}</h1>
            <p>{{ $employee ? $employee->full_name.' · '.$employee->employee_code : 'Add someone to your company roster' }}</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ $employee ? route('employees.show', $employee) : route('employees.index') }}">{{ __('Cancel') }}</a>
            <button class="btn pr" type="submit" form="employeeForm">{{ $employee ? 'Save changes' : 'Save employee' }}</button>
        </div>
    </div>

    <form id="employeeForm" class="ef" method="post" enctype="multipart/form-data" action="{{ $employee ? route('employees.update', $employee) : route('employees.store') }}" data-user-needs-parent="{{ ($employee && $employee->user?->roles->contains(fn ($role) => $role->requiresReportingManager())) ? '1' : '0' }}">
        @csrf
        @if($employee) @method('put') @endif

        <div class="ef-grid">
            <div class="ef-main">
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('Identity') }}</h3>
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
                            <p class="ef-code-note">Employee code is assigned automatically from the company name (6 characters, e.g. AT0001 for Autotech).</p>
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
                            <h3>{{ __('Contact') }}</h3>
                            <p>How you reach them at work</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c2">
                            <x-text-field name="email" label="Email" type="email" :value="$employee->email ?? ''" />
                            <x-text-field name="phone" label="Phone" :value="$employee->phone ?? ''" />
                            <x-text-field name="alternate_phone" label="Alternate phone" :value="$employee->alternate_phone ?? ''" />
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('Address') }}</h3>
                            <p>Where they live</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c2">
                            <x-text-field name="address" label="Address" :value="$employee->address ?? ''" />
                            <x-text-field name="city" label="City" :value="$employee->city ?? ''" />
                            <x-text-field name="state" label="State" :value="$employee->state ?? ''" />
                            <x-text-field name="postal_code" label="Postal code" :value="$employee->postal_code ?? ''" />
                        </div>
                    </div>
                </section>

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('Placement') }}</h3>
                            <p>Where they sit in the organisation</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c3">
                            <x-select-field name="department_id" label="Department" required>
                                <option value="">Select</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $employee->department_id ?? '') == $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </x-select-field>
                            <x-select-field name="designation_id" label="Designation" required>
                                <option value="">Select</option>
                                @foreach($designations as $designation)
                                    <option value="{{ $designation->id }}" data-category="{{ $designation->employeeType?->name }}" @selected(old('designation_id', $employee->designation_id ?? '') == $designation->id)>{{ $designation->name }}</option>
                                @endforeach
                            </x-select-field>
                            <div class="mb-3">
                                <label class="form-label" for="designationCategory">Category</label>
                                <input class="form-control" id="designationCategory" value="{{ $employee?->employeeType?->name }}" readonly>
                                <div class="form-text">Inherited from the designation</div>
                            </div>
                            <x-select-field name="employment_status_id" label="Status" required>
                                <option value="">Select</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" @selected(old('employment_status_id', $employee->employment_status_id ?? '') == $status->id)>{{ $status->name }}</option>
                                @endforeach
                            </x-select-field>
                        </div>
                        <div class="ef-role">
                            <x-select-field name="role_id" label="Role" data-role-list>
                                <option value="">No role</option>
                                @foreach($roles as $role)
                                    @php
                                        $roleNote = match (true) {
                                            $role->requiresReportingManager() && $role->appearsInManagerList() => ' · parent required · can be a parent',
                                            $role->requiresReportingManager() => ' · parent required',
                                            $role->appearsInManagerList() => ' · can be a parent',
                                            default => '',
                                        };
                                    @endphp
                                    <option
                                        value="{{ $role->id }}"
                                        data-has-parent="{{ $role->requiresReportingManager() ? '1' : '0' }}"
                                        data-has-child="{{ $role->appearsInManagerList() ? '1' : '0' }}"
                                        @selected(old('role_id', $employee->role_id ?? '') == $role->id)
                                    >{{ $role->name }}{{ $roleNote }}</option>
                                @endforeach
                            </x-select-field>
                            <p class="form-text">Role is assigned here on the employee. Staff sign in with this person’s email. A new login starts with password <code>password</code>.</p>
                        </div>
                        <div class="ef-parent">
                            <x-select-field name="reporting_manager_id" label="Parent" :required="$needsReportingManager ?? false" data-parent-list>
                                <option value="">{{ ($needsReportingManager ?? false) ? 'Select parent' : 'No parent' }}</option>
                                @foreach($managers as $manager)
                                    @php
                                        $parentRole = ($manager->role && $manager->role->appearsInManagerList())
                                            ? $manager->role
                                            : $manager->user?->roles->first(fn ($role) => $role->appearsInManagerList());
                                    @endphp
                                    <option value="{{ $manager->id }}" @selected(old('reporting_manager_id', $employee->reporting_manager_id ?? '') == $manager->id)>{{ $manager->full_name }} · {{ $manager->employee_code }}@if($parentRole) · {{ $parentRole->name }}@endif</option>
                                @endforeach
                            </x-select-field>
                            <p class="form-text" id="parentHint" data-empty="{{ $managers->isEmpty() ? '1' : '0' }}">
                                @if($managers->isEmpty())
                                    Nobody is listed yet. Save a person with a role that has “It has child” on, and they will show up here.
                                @elseif($needsReportingManager ?? false)
                                    This role has a parent. Choose someone whose role has “It has child” on.
                                @else
                                    Who this person works under. The list is only people whose role has “It has child” on.
                                @endif
                            </p>
                        </div>
                    </div>
                </section>

                {{-- Working times stay on Hours & pay. Not on create or edit.
                @include('employees._times', [
                    'employee' => $employee,
                    'times' => $times,
                    'canEditTimes' => true,
                    'standalone' => false,
                ])
                --}}

                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3>{{ __('Employment terms') }}</h3>
                            <p>Joining, probation, notice, and how pay is counted</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="g c3">
                            <x-text-field name="joining_date" label="Joining date" type="date" :value="optional($employee?->joining_date)->toDateString()" required />
                            <x-text-field name="confirmation_date" label="Confirmation date" type="date" :value="optional($employee?->confirmation_date)->toDateString()" />
                            <x-text-field name="probation_days" label="Probation days" type="number" :value="$employee->probation_days ?? ''" />
                            <x-text-field name="notice_period_days" label="Notice period days" type="number" :value="$employee->notice_period_days ?? ''" />
                            <x-select-field name="salary_type" label="Salary type" required>
                                <option value="">Select</option>
                                <option value="hours" @selected(old('salary_type', $employee->salary_type ?? '') === 'hours')>Hours based</option>
                                <option value="piece" @selected(old('salary_type', $employee->salary_type ?? '') === 'piece')>Piece based</option>
                            </x-select-field>
                        </div>
                        <p class="ef-hint mt-2 mb-0">Hours based people go on salary sheets. Piece based people stay on attendance only — salary is not generated for them.</p>
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
                                <h3>{{ __('Custom fields') }}</h3>
                                <p>Extra details for this company</p>
                            </div>
                        </header>
                        <div class="cb">
                            <div class="g c2">
                                @foreach($customFields as $field)
                                    @php $current = optional($employee?->customValues?->firstWhere('employee_custom_field_id', $field->id))->value; @endphp
                                    <div>
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
                            <h3>{{ __('Photograph') }}</h3>
                            <p>Used on the attendance list and sent to the terminal</p>
                        </div>
                    </header>
                    <div class="cb">
                        <div class="ef-photo-row">
                            @if($employee?->photograph_path)
                                <button type="button" class="att-zoom ef-thumb" data-photo="{{ route('employees.photo', $employee) }}" title="View photo">
                                    <img src="{{ route('employees.photo', $employee) }}" alt="{{ $employee->full_name }}">
                                </button>
                            @else
                                <p class="ed-empty">No photo uploaded yet.</p>
                            @endif
                            <div class="ef-photo-up">
                                <x-text-field name="photograph" label="Upload photo" type="file" accept="image/jpeg,image/png,image/webp" />
                            </div>
                        </div>
                        @if($employee && $terminals->isNotEmpty())
                            <p class="ef-photo-note">Saving also sends this photo to {{ $terminals->pluck('name')->join(', ') }}.</p>
                        @elseif($employee)
                            <p class="ef-photo-note">Saving also sends this photo to the company terminal.</p>
                        @endif
                    </div>
                </section>

                <section class="card ef-sec ef-help">
                    <header>
                        <div>
                            <h3>{{ __('Before you save') }}</h3>
                        </div>
                    </header>
                    <div class="cb">
                        <ul>
                            <li>Employee code is auto-generated and locked after save.</li>
                            <li>Category comes from the designation you pick.</li>
                            <li>Required fields are marked with a red star.</li>
                            <li>Role is set on the employee. There is no separate Users page.</li>
                        </ul>
                    </div>
                </section>
            </aside>
        </div>

        <div id="attView" class="att-view" hidden>
            <button type="button" class="att-view-x btn" data-close>{{ __('Close') }}</button>
            <img id="attViewImg" alt="Employee photo">
        </div>

        <div class="ef-bar">
            <a class="btn" href="{{ $employee ? route('employees.show', $employee) : route('employees.index') }}">{{ __('Cancel') }}</a>
            <button class="btn pr" type="submit">{{ $employee ? 'Save changes' : 'Save employee' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function ($) {
    function paintSunday(root) {
        if (!root) return;
        var box = root.querySelector('[data-sunday]');
        if (!box) return;
        var on = box.checked;
        var night = root.querySelector('[data-kind]:checked') && root.querySelector('[data-kind]:checked').value === 'night';
        root.querySelectorAll('[data-sunday-times]').forEach(function (el) { el.hidden = !on; });
        root.querySelectorAll('[data-sunday-off]').forEach(function (el) { el.hidden = on; });
        root.querySelectorAll('[data-sunday-times] input').forEach(function (input) {
            input.required = on && (night || input.name.indexOf('break') === -1);
        });
        var label = root.querySelector('.et-switch-txt');
        if (label) label.textContent = on ? 'On' : 'Off';
    }
    function applyKind(root) {
        if (!root) return;
        var picked = root.querySelector('[data-kind]:checked');
        var hold = root.querySelector('[data-kind-clocks]');
        if (!picked || !hold) return;
        var night = picked.value === 'night';
        function set(name, value) {
            var input = root.querySelector('[name="' + name + '"]');
            if (input) input.value = value || '';
        }
        set('weekday_start', night ? hold.dataset.nightStart : hold.dataset.dayStart);
        set('weekday_end', night ? hold.dataset.nightEnd : hold.dataset.dayEnd);
        set('sunday_start', night ? hold.dataset.nightSundayStart : hold.dataset.daySundayStart);
        set('sunday_end', night ? hold.dataset.nightSundayEnd : hold.dataset.daySundayEnd);
        set('weekday_break_start', night ? hold.dataset.nightBreakStart : hold.dataset.dayBreakStart);
        set('weekday_break_end', night ? hold.dataset.nightBreakEnd : hold.dataset.dayBreakEnd);
        set('sunday_break_start', night ? hold.dataset.nightSundayBreakStart : hold.dataset.daySundayBreakStart);
        set('sunday_break_end', night ? hold.dataset.nightSundayBreakEnd : hold.dataset.daySundayBreakEnd);
        var badge = root.querySelector('[data-kind-badge]');
        if (badge) {
            badge.textContent = night ? 'Night' : 'Day';
            badge.classList.toggle('is-night', night);
            badge.classList.toggle('is-day', !night);
        }
        var title = root.querySelector('[data-sunday-title]');
        if (title) title.textContent = night ? 'Sunday night' : 'Sunday';
        paintSunday(root);
    }
    paintSunday(document.getElementById('empTimes'));
    document.addEventListener('change', function (event) {
        var root = event.target.closest('#empTimes');
        if (!root) return;
        if (event.target.matches('[data-sunday]')) paintSunday(root);
        if (event.target.matches('[data-kind]')) applyKind(root);
    });

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

    function syncParentFromRole() {
        const form = document.getElementById('employeeForm');
        const role = document.getElementById('role_id');
        const parent = document.getElementById('reporting_manager_id');
        const hint = document.getElementById('parentHint');
        const label = document.querySelector('label[for="reporting_manager_id"]');
        if (!form || !role || !parent || !hint || !label) return;

        const picked = role.options[role.selectedIndex];
        const needs = (picked && picked.getAttribute('data-has-parent') === '1')
            || form.getAttribute('data-user-needs-parent') === '1';
        parent.required = needs;

        let star = label.querySelector('.text-danger');
        if (needs && !star) {
            star = document.createElement('span');
            star.className = 'text-danger';
            star.textContent = '*';
            label.append(' ', star);
        } else if (!needs && star) {
            star.remove();
        }

        const blank = parent.querySelector('option[value=""]');
        if (blank && parent.value === '') {
            blank.textContent = needs ? 'Select parent' : 'No parent';
            if ($.fn.select2 && $(parent).data('select2')) {
                $(parent).trigger('change.select2');
            }
        }

        if (needs) {
            hint.textContent = 'This role has a parent. Choose someone whose role has “It has child” on.';
        } else if (hint.getAttribute('data-empty') === '1') {
            hint.textContent = 'Nobody is listed yet. Save a person with a role that has “It has child” on, and they will show up here.';
        } else {
            hint.textContent = 'Who this person works under. The list is only people whose role has “It has child” on.';
        }
    }

    $(syncParentFromRole);
    $(document).on('change', '#role_id', syncParentFromRole);
})(jQuery);
</script>
@endpush
