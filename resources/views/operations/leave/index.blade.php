@extends('layouts.app')
@section('title', 'Leave Management')
@section('content')
    @php
        $statusLabels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
        $statusClass = [
            'pending' => 'wn',
            'approved' => 'ok',
            'rejected' => 'er',
        ];
    @endphp

    <div class="ph">
        <div>
            <h1>Leave Management</h1>
            <p>Staff record leave for employees. Employees do not apply themselves.</p>
        </div>
        <div class="ac">
            @if($canEdit)
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#leaveTypeModal">
                    <x-i name="plus" :size="14" />Add type
                </button>
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#leaveRecordModal">
                    <x-i name="plus" :size="14" />Record leave
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div class="lv-stats g c4">
        <div class="card kp">
            <small><span class="lv-dot pending"></span>Pending</small>
            <b>{{ $stats['pending'] }}</b>
            <span>Waiting for decision</span>
        </div>
        <div class="card kp">
            <small><span class="lv-dot approved"></span>Approved</small>
            <b>{{ $stats['approved'] }}</b>
            <span>Counted on roster</span>
        </div>
        <div class="card kp">
            <small><span class="lv-dot rejected"></span>Rejected</small>
            <b>{{ $stats['rejected'] }}</b>
            <span>Not counted</span>
        </div>
        <div class="card kp">
            <small><span class="lv-dot types"></span>Leave types</small>
            <b>{{ $stats['types'] }}</b>
            <span>Policies for this company</span>
        </div>
    </div>

    <section class="card lv-types">
        <header>
            <div>
                <h3>Leave types</h3>
                <p>Yearly days of 0 means no cap. Offs and holidays are skipped unless counted.</p>
            </div>
            @if($canEdit)
                <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#leaveTypeModal">
                    <x-i name="plus" :size="12" />New
                </button>
            @endif
        </header>
        <div class="cb">
            @if($types->isEmpty())
                <p class="lv-empty">No leave types yet. Add Casual, Sick, or unpaid policies first.</p>
            @else
                <div class="lv-chips">
                    @foreach($types as $type)
                        <article class="lv-chip">
                            <div class="lv-chip-code">{{ $type->code }}</div>
                            <div class="lv-chip-body">
                                <div class="lv-chip-top">
                                    <b title="{{ $type->name }}">{{ $type->name }}</b>
                                    <span class="b {{ $type->is_paid ? 'ok' : '' }}">{{ $type->is_paid ? 'Paid' : 'Unpaid' }}</span>
                                </div>
                                <div class="lv-chip-meta">
                                    <span>{{ (float) $type->annual_days === 0.0 ? 'No yearly cap' : $type->annual_days.' days / year' }}</span>
                                    @if($type->counts_weekly_off)
                                        <span class="lv-chip-sep">·</span>
                                        <span>Counts off</span>
                                    @endif
                                    @if($type->counts_holidays)
                                        <span class="lv-chip-sep">·</span>
                                        <span>Counts holidays</span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="card lv" id="leaveList">
        <form class="tbar lv-bar" method="get" action="{{ route('leave.index') }}" id="leaveFilters">
            <div class="sr lv-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search employee…" autocomplete="off">
            </div>
            <select class="in" name="leave_type_id" data-filter>
                <option value="">All types</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected((string) $filters['leave_type_id'] === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            <select class="in" name="status" data-filter>
                <option value="">All statuses</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <a class="btn lv-reset" href="{{ route('leave.index') }}" title="Reset filters">
                <x-i name="reset" :size="14" />Reset
            </a>
            <div class="sp"></div>
            <span class="lv-meta">{{ $requests->total() }} {{ \Illuminate\Support\Str::plural('request', $requests->total()) }}</span>
        </form>

        <div class="tw lv-table">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        @if($canEdit)<th class="lv-act">Action</th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $row)
                    @php
                        $badge = $statusClass[$row->status] ?? '';
                        $label = $statusLabels[$row->status] ?? ucfirst($row->status);
                    @endphp
                    <tr class="{{ $row->status === 'pending' ? 'lv-row-pending' : '' }}">
                        <td>
                            @if($row->employee)
                                <div class="who">
                                    <x-employee-avatar :employee="$row->employee" :size="32" />
                                    <div>
                                        <b>{{ $row->employee->full_name }}</b>
                                        <small>{{ $row->employee->employee_code }}@if($row->employee->department) · {{ $row->employee->department->name }}@endif</small>
                                    </div>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="lv-type">{{ $row->leaveType?->code ?? '—' }}</span>
                            <small class="lv-type-name">{{ $row->leaveType?->name }}</small>
                        </td>
                        <td class="lv-dates">
                            <b>{{ $row->starts_on->format('d M') }} – {{ $row->ends_on->format('d M Y') }}</b>
                            @if($row->starts_on->equalTo($row->ends_on))
                                <small>{{ $row->starts_on->format('l') }}</small>
                            @endif
                        </td>
                        <td class="lv-days">{{ $row->days }}</td>
                        <td>
                            <span class="lv-reason" title="{{ $row->reason }}">{{ \Illuminate\Support\Str::limit($row->reason, 36) }}</span>
                        </td>
                        <td><span class="b {{ $badge }}">{{ $label }}</span></td>
                        @if($canEdit)
                            <td class="lv-act">
                                <div class="lv-ops">
                                    @if($row->status === 'pending')
                                        <form method="post" action="{{ route('leave.decide', $row) }}" class="lv-decide" data-confirm="Approve leave for {{ $row->employee?->full_name }}?">
                                            @csrf
                                            <input type="hidden" name="decision" value="approved">
                                            @include('operations.leave._retain', ['filters' => $filters])
                                            <button class="btn sm pr" type="submit" title="Approve">Approve</button>
                                        </form>
                                        <form method="post" action="{{ route('leave.decide', $row) }}" class="lv-decide" data-confirm="Reject leave for {{ $row->employee?->full_name }}?">
                                            @csrf
                                            <input type="hidden" name="decision" value="rejected">
                                            @include('operations.leave._retain', ['filters' => $filters])
                                            <button class="btn sm" type="submit" title="Reject">Reject</button>
                                        </form>
                                    @endif
                                    <button
                                        class="ib"
                                        type="button"
                                        title="Edit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#leaveRecordModal"
                                        data-mode="edit"
                                        data-action="{{ route('leave.update', $row) }}"
                                        data-employee-id="{{ $row->employee_id }}"
                                        data-leave-type-id="{{ $row->leave_type_id }}"
                                        data-starts-on="{{ $row->starts_on->toDateString() }}"
                                        data-ends-on="{{ $row->ends_on->toDateString() }}"
                                        data-reason="{{ $row->reason }}"
                                    >
                                        <x-i name="pen" :size="14" />
                                    </button>
                                    <form method="post" action="{{ route('leave.destroy', $row) }}" data-confirm="Delete leave for {{ $row->employee?->full_name }}? This cannot be undone.">
                                        @csrf @method('delete')
                                        @include('operations.leave._retain', ['filters' => $filters])
                                        <button class="ib er" type="submit" title="Delete"><x-i name="trash" :size="14" /></button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canEdit ? 7 : 6 }}" class="lv-empty-row">No leave requests match these filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pgn">
            <span>{{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }}</span>
            <div>{{ $requests->links() }}</div>
        </div>
    </section>

    @if($canEdit)
        <div class="modal fade" id="leaveTypeModal" tabindex="-1" aria-labelledby="leaveTypeTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('leave.types.store') }}">
                    @csrf
                    @include('operations.leave._retain', ['filters' => $filters])
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveTypeTitle">Add leave type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body lv-modal-body">
                        <p class="lv-modal-help">Yearly days of 0 means unlimited. Tick offs/holidays only if those days should reduce the balance.</p>
                        <div class="lv-modal-grid">
                            <div>
                                <label class="form-label">Name *</label>
                                <input class="in w-100" name="name" required value="{{ old('name') }}" placeholder="Casual">
                            </div>
                            <div>
                                <label class="form-label">Code *</label>
                                <input class="in w-100" name="code" required value="{{ old('code') }}" placeholder="CL" maxlength="40">
                            </div>
                        </div>
                        <div class="lv-modal-grid">
                            <div>
                                <label class="form-label">Yearly days</label>
                                <input class="in w-100" type="number" step="0.5" min="0" name="annual_days" value="{{ old('annual_days', 0) }}">
                            </div>
                            <div>
                                <label class="form-label">Paid</label>
                                <select class="in w-100" name="is_paid">
                                    <option value="1" @selected(old('is_paid', '1') == '1')>Paid</option>
                                    <option value="0" @selected(old('is_paid') === '0')>Unpaid</option>
                                </select>
                            </div>
                        </div>
                        <div class="lv-checks">
                            <label class="lv-check"><input type="checkbox" name="counts_weekly_off" value="1" @checked(old('counts_weekly_off'))><span>Count weekly off</span></label>
                            <label class="lv-check"><input type="checkbox" name="counts_holidays" value="1" @checked(old('counts_holidays'))><span>Count holidays</span></label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Save type</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="leaveRecordModal" tabindex="-1" aria-labelledby="leaveRecordTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('leave.store') }}" id="leaveRecordForm">
                    @csrf
                    <input type="hidden" name="_method" id="leaveMethod" value="POST">
                    @include('operations.leave._retain', ['filters' => $filters])
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveRecordTitle">Record leave</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body lv-modal-body">
                        <p class="lv-modal-help" id="leaveEditNote" hidden>Editing resets the request to pending for approval again.</p>
                        <label class="form-label">Employee *</label>
                        <select class="in w-100" name="employee_id" id="leaveEmployee" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <label class="form-label">Leave type *</label>
                        <select class="in w-100" name="leave_type_id" id="leaveTypeId" required>
                            <option value="">Select type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->code }} — {{ $type->name }}</option>
                            @endforeach
                        </select>
                        <div class="lv-modal-grid">
                            <div>
                                <label class="form-label">From *</label>
                                <input class="in w-100" type="date" name="starts_on" id="leaveStartsOn" required value="{{ old('starts_on') }}">
                            </div>
                            <div>
                                <label class="form-label">To *</label>
                                <input class="in w-100" type="date" name="ends_on" id="leaveEndsOn" required value="{{ old('ends_on') }}">
                            </div>
                        </div>
                        <label class="form-label">Reason *</label>
                        <input class="in w-100" name="reason" id="leaveReason" required maxlength="500" value="{{ old('reason') }}" placeholder="Short note for HR">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr" id="leaveSubmitBtn">Save leave</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function ($) {
    const form = $('#leaveFilters');
    if (form.length) {
        let timer = null;
        form.on('change', '[data-filter]', function () {
            form.trigger('submit');
        });
        form.on('input', 'input[name="q"]', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { form.trigger('submit'); }, 350);
        });
    }

    const modal = document.getElementById('leaveRecordModal');
    if (!modal) return;

    const recordForm = document.getElementById('leaveRecordForm');
    const methodInput = document.getElementById('leaveMethod');
    const title = document.getElementById('leaveRecordTitle');
    const note = document.getElementById('leaveEditNote');
    const submitBtn = document.getElementById('leaveSubmitBtn');
    const storeUrl = @json(route('leave.store'));

    function setDateValue(input, value) {
        if (!input) return;
        input.value = value || '';
        if (input._flatpickr) {
            input._flatpickr.setDate(value || null, true);
        }
    }

    function setSelectValue(input, value) {
        if (!input) return;
        const next = value == null ? '' : String(value);
        input.value = next;
        const $el = $(input);
        if ($el.data('select2')) {
            $el.val(next).trigger('change');
        } else {
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function fillLeaveForm(btn) {
        const mode = btn && btn.getAttribute('data-mode');

        if (mode === 'edit' && btn) {
            title.textContent = 'Edit leave';
            note.hidden = false;
            submitBtn.textContent = 'Update leave';
            recordForm.action = btn.getAttribute('data-action');
            methodInput.value = 'PUT';
            setSelectValue(document.getElementById('leaveEmployee'), btn.getAttribute('data-employee-id'));
            setSelectValue(document.getElementById('leaveTypeId'), btn.getAttribute('data-leave-type-id'));
            setDateValue(document.getElementById('leaveStartsOn'), btn.getAttribute('data-starts-on'));
            setDateValue(document.getElementById('leaveEndsOn'), btn.getAttribute('data-ends-on'));
            document.getElementById('leaveReason').value = btn.getAttribute('data-reason') || '';
            return;
        }

        title.textContent = 'Record leave';
        note.hidden = true;
        submitBtn.textContent = 'Save leave';
        recordForm.action = storeUrl;
        methodInput.value = 'POST';
        setSelectValue(document.getElementById('leaveEmployee'), '');
        setSelectValue(document.getElementById('leaveTypeId'), '');
        setDateValue(document.getElementById('leaveStartsOn'), '');
        setDateValue(document.getElementById('leaveEndsOn'), '');
        document.getElementById('leaveReason').value = '';
    }

    modal.addEventListener('show.bs.modal', function (event) {
        fillLeaveForm(event.relatedTarget);
    });

    modal.addEventListener('shown.bs.modal', function (event) {
        fillLeaveForm(event.relatedTarget);
    });
})(jQuery);
</script>
@endpush
