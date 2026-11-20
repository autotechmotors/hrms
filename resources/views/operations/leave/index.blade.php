@extends('layouts.app')
@section('title', 'Leave Management')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Leave Management') }}</h1>
            <p>{{ __('An employee can request leave. Super Admin, HR Admin, and that employee’s parent approve it or reject it with a reason.') }}</p>
        </div>
        <div class="ac">
            @if($canEdit)
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#leaveTypeModal" data-mode="create">
                    <x-i name="plus" :size="14" />{{ __('Add type') }}
                </button>
            @endif
            @if($canEdit || $canApply)
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#leaveRecordModal" data-mode="create">
                    <x-i name="plus" :size="14" />{{ $canEdit ? __('Record leave') : __('Request leave') }}
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div id="leaveBody">
        @include('operations.leave._body')
    </div>

    @if($canEdit)
        <div class="modal fade" id="leaveTypeModal" tabindex="-1" aria-labelledby="leaveTypeTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('leave.types.store') }}" id="leaveTypeForm">
                    @csrf
                    <input type="hidden" name="_method" id="leaveTypeMethod" value="POST">
                    @include('operations.leave._retain', ['filters' => $filters])
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveTypeTitle">{{ __('Add leave type') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body lv-modal-body">
                        <p class="lv-modal-help">{{ __('Cutoff takes one day of pay for each leave day. No cutoff pays that day. Weekly offs and company holidays are not counted as leave.') }}</p>
                        <label class="form-label">{{ __('Department') }}</label>
                        <select class="in w-100" name="department_ids[]" id="leaveTypeDepartment" multiple>
                            <option value="">{{ __('All departments') }}</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                        <p class="lv-modal-help">{{ __('Pick one or more departments. Leave it empty to use this type for every department.') }}</p>
                        <div class="lv-modal-grid">
                            <div>
                                <label class="form-label">{{ __('Name') }} *</label>
                                <input class="in w-100" name="name" id="leaveTypeName" required maxlength="120" placeholder="Casual">
                            </div>
                            <div>
                                <label class="form-label">{{ __('Code') }} *</label>
                                <input class="in w-100" name="code" id="leaveTypeCode" required maxlength="40" placeholder="CL">
                            </div>
                        </div>
                        <label class="form-label">{{ __('Salary cutoff') }} *</label>
                        <select class="in w-100" name="cuts_pay" id="leaveTypeCutoff" required>
                            <option value="0">{{ __('No — pay the day') }}</option>
                            <option value="1">{{ __('Yes — cut the day from salary') }}</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn pr" id="leaveTypeSubmit">{{ __('Save type') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canEdit || $canApply)
        <div class="modal fade" id="leaveRecordModal" tabindex="-1" aria-labelledby="leaveRecordTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('leave.store') }}" id="leaveRecordForm">
                    @csrf
                    <input type="hidden" name="_method" id="leaveMethod" value="POST">
                    @include('operations.leave._retain', ['filters' => $filters])
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveRecordTitle">{{ __('Request leave') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body lv-modal-body">
                        <p class="lv-modal-help" id="leaveEditNote" hidden>{{ __('Editing resets the request to pending for approval again.') }}</p>
                        <label class="form-label">{{ __('Employee') }} *</label>
                        @if($canEdit)
                            <select class="in w-100" name="employee_id" id="leaveEmployee" required>
                                <option value="">{{ __('Select employee') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" data-department="{{ $employee->department_id }}" data-department-name="{{ $employee->department?->name }}">{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="employee_id" id="leaveEmployee" value="{{ $ownEmployee?->id }}">
                            <input class="in w-100" value="{{ $ownEmployee?->employee_code }} — {{ $ownEmployee?->full_name }}" readonly>
                        @endif
                        <label class="form-label">{{ __('Leave type') }} *</label>
                        <select class="in w-100" name="leave_type_id" id="leaveTypeId" required>
                            <option value="">{{ __('Select type') }}</option>
                            @foreach($typeChoices as $type)
                                <option value="{{ $type->id }}" data-department="{{ $type->department_id }}">{{ $type->code }} — {{ $type->name }}{{ $type->is_paid ? '' : ' · '.__('Cutoff') }}</option>
                            @endforeach
                        </select>
                        <p class="lv-modal-help" id="leaveTypeHint"></p>
                        <div class="lv-modal-grid">
                            <div>
                                <label class="form-label">{{ __('From') }} *</label>
                                <input class="in w-100" type="date" name="starts_on" id="leaveStartsOn" required>
                            </div>
                            <div>
                                <label class="form-label">{{ __('To') }} *</label>
                                <input class="in w-100" type="date" name="ends_on" id="leaveEndsOn" required>
                            </div>
                        </div>
                        <label class="form-label">{{ __('Reason') }} *</label>
                        <input class="in w-100" name="reason" id="leaveReason" required maxlength="500" placeholder="{{ __('Short note') }}">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn pr" id="leaveSubmitBtn">{{ __('Send request') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="leaveRejectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="#" id="leaveRejectForm">
                    @csrf
                    @include('operations.leave._retain', ['filters' => $filters])
                    <input type="hidden" name="decision" value="rejected">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Reject leave') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="lv-modal-help" id="leaveRejectName"></p>
                        <label class="form-label">{{ __('Rejection reason') }} *</label>
                        <textarea class="in w-100" name="decision_note" id="leaveRejectNote" required maxlength="500" rows="3"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn pr">{{ __('Reject') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function ($) {
    const body = document.getElementById('leaveBody');
    let searchTimer = null;
    let paintLeaveTypes = function () {};

    function bindLeaveSelects() {
        if (!window.jQuery || !window.jQuery.fn.select2 || !body) return;
        window.jQuery(body).find('select').not('[data-native]').each(function () {
            const select = window.jQuery(this);
            if (select.data('select2')) return;
            const blank = select.find('option[value=""]').first();
            select.select2({
                width: '100%',
                dropdownParent: window.jQuery(document.body),
                minimumResultsForSearch: select.find('option').length > 8 ? 0 : Infinity,
                placeholder: blank.length ? blank.text() : undefined,
            });
        });
    }

    function reloadLeave() {
        if (!body) return;
        const filterForm = document.getElementById('leaveFilters');
        const url = new URL(filterForm ? filterForm.action : window.location.href, window.location.origin);
        if (filterForm) {
            new FormData(filterForm).forEach(function (value, key) {
                if (value) url.searchParams.set(key, value);
                else url.searchParams.delete(key);
            });
        }
        const clean = new URL(url.toString());
        window.history.replaceState({}, '', clean.pathname + clean.search);
        url.searchParams.set('partial', '1');
        window.jQuery.ajax({
            url: url.toString(),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (html) {
                body.innerHTML = html;
                bindLeaveSelects();
                paintLeaveTypes('');
                document.dispatchEvent(new CustomEvent('hrms:list-updated', { bubbles: true }));
            }
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.closest && event.target.closest('#leaveFilters') && event.target.matches('[data-filter]')) {
            reloadLeave();
        }
    });
    document.addEventListener('input', function (event) {
        if (!event.target.matches || !event.target.matches('#leaveFilters input[name="q"]')) return;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(reloadLeave, 350);
    });

    function sendForm(form) {
        const data = new FormData(form);
        window.jQuery.ajax({
            url: form.action,
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            success: function (body) {
                window.toast((body && body.message) || 'Saved');
                form.querySelectorAll('.modal').forEach(function () {});
                document.querySelectorAll('.modal.show').forEach(function (node) {
                    const modal = bootstrap.Modal.getInstance(node);
                    if (modal) modal.hide();
                });
                reloadLeave();
            },
            error: function (xhr) {
                const errors = xhr.responseJSON && xhr.responseJSON.errors;
                const first = errors ? Object.values(errors)[0] : null;
                window.toast((first && first[0]) || (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save', 'er');
            }
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form || !form.closest || !form.closest('#leaveBody, #leaveTypeModal, #leaveRecordModal, #leaveRejectModal')) return;
        if (form.id === 'leaveFilters') {
            event.preventDefault();
            reloadLeave();
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        if (form.dataset.confirm && form.dataset.confirmed !== '1') {
            window.askConfirm(form.dataset.confirm, { danger: true }).then(function (ok) {
                if (!ok) return;
                form.dataset.confirmed = '1';
                sendForm(form);
            });
            return;
        }
        sendForm(form);
    }, true);

    const typeModal = document.getElementById('leaveTypeModal');
    const typeStore = @json(route('leave.types.store'));
    if (typeModal) {
        typeModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            const form = document.getElementById('leaveTypeForm');
            const method = document.getElementById('leaveTypeMethod');
            const editing = btn && btn.getAttribute('data-mode') === 'edit';
            document.getElementById('leaveTypeTitle').textContent = editing ? @json(__('Edit leave type')) : @json(__('Add leave type'));
            document.getElementById('leaveTypeSubmit').textContent = editing ? @json(__('Update type')) : @json(__('Save type'));
            form.action = editing ? btn.getAttribute('data-action') : typeStore;
            method.value = editing ? 'PUT' : 'POST';
            const department = document.getElementById('leaveTypeDepartment');
            const cutoff = document.getElementById('leaveTypeCutoff');
            const departmentId = editing ? (btn.getAttribute('data-department-id') || '') : '';
            document.getElementById('leaveTypeName').value = editing ? (btn.getAttribute('data-name') || '') : '';
            document.getElementById('leaveTypeCode').value = editing ? (btn.getAttribute('data-code') || '') : '';
            cutoff.value = editing ? (btn.getAttribute('data-cuts-pay') || '0') : '0';
            if (window.jQuery) {
                window.jQuery(department).val(departmentId ? [departmentId] : null).trigger('change');
                window.jQuery(cutoff).trigger('change');
            }
        });
    }

    const modal = document.getElementById('leaveRecordModal');
    if (modal) {
        const recordForm = document.getElementById('leaveRecordForm');
        const methodInput = document.getElementById('leaveMethod');
        const title = document.getElementById('leaveRecordTitle');
        const note = document.getElementById('leaveEditNote');
        const submitBtn = document.getElementById('leaveSubmitBtn');
        const storeUrl = @json(route('leave.store'));
        const ownDepartment = @json((string) ($ownEmployee->department_id ?? ''));
        const ownDepartmentName = @json((string) ($ownEmployee?->department?->name ?? ''));
        const typeCopy = {
            pickEmployee: @json(__('Select an employee first')),
            pickType: @json(__('Select type')),
            none: @json(__('No leave type for this department')),
            wait: @json(__('Select an employee. Only that department’s leave types are shown.')),
            empty: @json(__(':department has no leave type yet.')),
            ready: @json(__('Showing leave types for :department.')),
        };

        function setDateValue(input, value) {
            if (!input) return;
            input.value = value || '';
            if (input._flatpickr) input._flatpickr.setDate(value || null, true);
        }

        function setSelectValue(input, value) {
            if (!input || input.tagName !== 'SELECT') return;
            const next = value == null ? '' : String(value);
            input.value = next;
            const $el = $(input);
            if ($el.data('select2')) $el.val(next).trigger('change');
            else input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        paintLeaveTypes = function (selected) {
            const select = document.getElementById('leaveTypeId');
            const source = document.getElementById('leaveTypeChoiceSource');
            if (!select || !source) return;
            const employee = document.getElementById('leaveEmployee');
            let chosen = Boolean(employee && employee.tagName !== 'SELECT');
            let department = chosen ? ownDepartment : '';
            let departmentName = chosen ? ownDepartmentName : '';
            if (employee && employee.tagName === 'SELECT') {
                const option = Array.from(employee.options).find(function (item) { return item.value === employee.value; });
                chosen = Boolean(option && option.value);
                department = chosen ? (option.getAttribute('data-department') || '') : '';
                departmentName = chosen ? (option.getAttribute('data-department-name') || '') : '';
            }
            const $el = window.jQuery(select);
            if ($el.data('select2')) $el.select2('destroy');
            const matched = [];
            source.content.querySelectorAll('option').forEach(function (type) {
                const typeDepartment = type.getAttribute('data-department') || '';
                if (!chosen && typeDepartment !== '') return;
                if (chosen && typeDepartment !== '' && typeDepartment !== department) return;
                matched.push(type.cloneNode(true));
            });
            const blankLabel = !chosen ? typeCopy.pickEmployee : (matched.length ? typeCopy.pickType : typeCopy.none);
            select.innerHTML = '';
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = blankLabel;
            select.appendChild(blank);
            matched.forEach(function (type) { select.appendChild(type); });
            const hint = document.getElementById('leaveTypeHint');
            if (hint) {
                const name = departmentName || 'This department';
                hint.textContent = !chosen
                    ? typeCopy.wait
                    : (matched.length ? typeCopy.ready : typeCopy.empty).replace(':department', name);
            }
            $el.select2({
                width: '100%',
                dropdownParent: window.jQuery(document.body),
                minimumResultsForSearch: select.options.length > 8 ? 0 : Infinity,
                placeholder: blankLabel,
            });
            const next = selected && select.querySelector('option[value="' + String(selected) + '"]') ? String(selected) : '';
            $el.val(next).trigger('change');
        };

        function fillLeaveForm(btn) {
            const mode = btn && btn.getAttribute('data-mode');
            if (mode === 'edit' && btn) {
                title.textContent = @json(__('Edit leave'));
                note.hidden = false;
                submitBtn.textContent = @json(__('Update leave'));
                recordForm.action = btn.getAttribute('data-action');
                methodInput.value = 'PUT';
                setSelectValue(document.getElementById('leaveEmployee'), btn.getAttribute('data-employee-id'));
                paintLeaveTypes(btn.getAttribute('data-leave-type-id'));
                setDateValue(document.getElementById('leaveStartsOn'), btn.getAttribute('data-starts-on'));
                setDateValue(document.getElementById('leaveEndsOn'), btn.getAttribute('data-ends-on'));
                document.getElementById('leaveReason').value = btn.getAttribute('data-reason') || '';
                return;
            }
            title.textContent = @json($canEdit ? 'Record leave' : 'Request leave');
            note.hidden = true;
            submitBtn.textContent = @json(__('Send request'));
            recordForm.action = storeUrl;
            methodInput.value = 'POST';
            setSelectValue(document.getElementById('leaveEmployee'), '');
            paintLeaveTypes('');
            setDateValue(document.getElementById('leaveStartsOn'), '');
            setDateValue(document.getElementById('leaveEndsOn'), '');
            document.getElementById('leaveReason').value = '';
        }

        modal.addEventListener('show.bs.modal', function (event) { fillLeaveForm(event.relatedTarget); });
        document.getElementById('leaveEmployee')?.addEventListener('change', function () {
            paintLeaveTypes(document.getElementById('leaveTypeId')?.value || '');
        });
        document.addEventListener('DOMContentLoaded', function () { paintLeaveTypes(''); });
    }

    const rejectModal = document.getElementById('leaveRejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('leaveRejectForm').action = btn ? btn.getAttribute('data-action') : '#';
            document.getElementById('leaveRejectName').textContent = btn ? (btn.getAttribute('data-name') || '') : '';
            document.getElementById('leaveRejectNote').value = '';
        });
    }

})(jQuery);
</script>
@endpush
