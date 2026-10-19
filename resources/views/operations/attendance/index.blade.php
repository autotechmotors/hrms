@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
    @php
        $prev = $date->copy()->subDay()->toDateString();
        $next = $date->copy()->addDay()->toDateString();
        $today = now()->toDateString();
        $isToday = $date->toDateString() === $today;
        $queryBase = array_filter([
            'q' => $filters['q'] ?: null,
            'department_id' => $filters['department_id'] ?: null,
            'status' => $filters['status'] ?: null,
        ]);
        $statusLabels = [
            'present' => 'Present',
            'late' => 'Late',
            'half_day' => 'Half day',
            'absent' => 'Absent',
            'on_leave' => 'On leave',
            'holiday' => 'Holiday',
            'weekly_off' => 'Weekly off',
            'unmarked' => 'Unmarked',
            'pending' => 'Not calculated',
        ];
        $statusClass = [
            'present' => 'ok',
            'late' => 'wn',
            'half_day' => 'wn',
            'absent' => 'er',
            'on_leave' => 'ac',
            'holiday' => 'ac',
            'weekly_off' => '',
            'unmarked' => 'wn',
            'pending' => '',
        ];
        $formatMinutes = function ($minutes) {
            if ($minutes === null || $minutes === '') {
                return '—';
            }
            $minutes = (int) $minutes;
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return $h > 0 ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm', $m);
        };
        $formatTime = function ($time) {
            return $time ? \Illuminate\Support\Str::of($time)->substr(0, 5) : '—';
        };
    @endphp

    <div class="ph">
        <div>
            <h1>Attendance</h1>
            <p>Daily punches and calculated status from shift, leave, holidays, and rules.</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('attendance.index', $queryBase + ['date' => $prev]) }}" title="Previous day">
                <x-i name="back" :size="14" />
            </a>
            <span class="att-date-label">{{ $date->format('D, d M Y') }}</span>
            <a class="btn" href="{{ route('attendance.index', $queryBase + ['date' => $next]) }}" title="Next day">→</a>
            @unless($isToday)
                <a class="btn" href="{{ route('attendance.index', $queryBase + ['date' => $today]) }}">Today</a>
            @endunless
            @if($canEdit)
                <form method="post" action="{{ route('attendance.recalculate') }}" class="att-inline">
                    @csrf
                    <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">
                    <button class="btn" type="submit" title="Recalculate this day">
                        <x-i name="reset" :size="14" />Calculate
                    </button>
                </form>
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#attRuleModal">
                    <x-i name="set" :size="14" />Rules
                </button>
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#attPunchModal" data-mode="create">
                    <x-i name="plus" :size="14" />Add punch
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div class="att-stats g c4">
        <div class="card kp">
            <small><span class="att-dot present"></span>Present</small>
            <b>{{ $stats['working'] }}</b>
            <span>{{ $stats['late'] }} late · {{ $stats['half_day'] }} half day</span>
        </div>
        <div class="card kp">
            <small><span class="att-dot absent"></span>Absent</small>
            <b>{{ $stats['absent'] }}</b>
            <span>Marked absent for the day</span>
        </div>
        <div class="card kp">
            <small><span class="att-dot leave"></span>Leave / off</small>
            <b>{{ $stats['on_leave'] + $stats['holiday'] + $stats['weekly_off'] }}</b>
            <span>{{ $stats['on_leave'] }} leave · {{ $stats['holiday'] }} holiday · {{ $stats['weekly_off'] }} off</span>
        </div>
        <div class="card kp">
            <small><span class="att-dot pending"></span>Pending</small>
            <b>{{ $stats['pending'] + $stats['unmarked'] }}</b>
            <span>{{ $stats['pending'] }} not calculated · {{ $stats['unmarked'] }} unmarked</span>
        </div>
    </div>

    <section class="card att" id="attendanceList">
        <form class="tbar att-bar" method="get" action="{{ route('attendance.index') }}" id="attendanceFilters">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <div class="sr att-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name, code…" autocomplete="off">
            </div>
            <select class="in" name="department_id" data-filter>
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <select class="in" name="status" data-filter>
                <option value="">All statuses</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <a class="btn att-reset" href="{{ route('attendance.index', ['date' => $date->toDateString()]) }}" title="Reset filters">
                <x-i name="reset" :size="14" />Reset
            </a>
            <div class="sp"></div>
            <span class="att-meta">{{ $employees->total() }} {{ \Illuminate\Support\Str::plural('person', $employees->total()) }}</span>
        </form>

        <div class="tw att-table">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>In</th>
                        <th>Out</th>
                        <th>Worked</th>
                        <th>Status</th>
                        @if($canEdit)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    @php
                        $day = $days->get($employee->id);
                        $statusKey = $day?->status ?? 'pending';
                        $badge = $statusClass[$statusKey] ?? '';
                        $label = $statusLabels[$statusKey] ?? str_replace('_', ' ', $statusKey);
                    @endphp
                    <tr class="{{ $statusKey === 'absent' ? 'att-row-absent' : ($statusKey === 'pending' ? 'att-row-pending' : '') }}">
                        <td>
                            <div class="who">
                                <x-employee-avatar :employee="$employee" :size="32" />
                                <div>
                                    <b>{{ $employee->full_name }}</b>
                                    <small>{{ $employee->employee_code }}@if($employee->designation) · {{ $employee->designation->name }}@endif</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td class="att-time">{{ $formatTime($day?->punch_in) }}</td>
                        <td class="att-time">{{ $formatTime($day?->punch_out) }}</td>
                        <td class="att-mins">{{ $formatMinutes($day?->minutes_worked) }}</td>
                        <td>
                            <span class="b {{ $badge }}">{{ $label }}</span>
                            @if($day?->remarks)
                                <small class="att-note" title="{{ $day->remarks }}">{{ \Illuminate\Support\Str::limit($day->remarks, 28) }}</small>
                            @endif
                        </td>
                        @if($canEdit)
                            <td class="att-act">
                                <button
                                    class="ib"
                                    type="button"
                                    title="{{ $day ? 'Edit punch' : 'Add punch' }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#attPunchModal"
                                    data-mode="edit"
                                    data-employee-id="{{ $employee->id }}"
                                    data-punch-in="{{ $formatTime($day?->punch_in) !== '—' ? $formatTime($day?->punch_in) : '' }}"
                                    data-punch-out="{{ $formatTime($day?->punch_out) !== '—' ? $formatTime($day?->punch_out) : '' }}"
                                    data-remarks="{{ $day?->remarks }}"
                                >
                                    <x-i name="pen" :size="14" />
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canEdit ? 7 : 6 }}" class="att-empty">
                            No employees match this day and filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pgn">
            <span>{{ $employees->firstItem() ?? 0 }}–{{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }}</span>
            <div>{{ $employees->links() }}</div>
        </div>
    </section>

    @if($canEdit)
        <div class="modal fade" id="attPunchModal" tabindex="-1" aria-labelledby="attPunchTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('attendance.punch') }}" id="attPunchForm">
                    @csrf
                    <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">
                    @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                    @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                    @if($filters['status'])<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <div class="modal-header">
                        <h5 class="modal-title" id="attPunchTitle">Add punch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body att-modal-body">
                        <p class="att-modal-date">{{ $date->format('l, d M Y') }}</p>
                        <input type="hidden" name="employee_id" id="attEmployeeHidden" value="">
                        <label class="form-label">Employee *</label>
                        <select class="in w-100" id="attEmployee" required>
                            <option value="">Select employee</option>
                            @foreach($picker as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <div class="att-time-grid">
                            <div>
                                <label class="form-label">Punch in</label>
                                <input class="in w-100" type="time" name="punch_in" id="attPunchIn">
                            </div>
                            <div>
                                <label class="form-label">Punch out</label>
                                <input class="in w-100" type="time" name="punch_out" id="attPunchOut">
                            </div>
                        </div>
                        <label class="form-label">Remarks</label>
                        <input class="in w-100" name="remarks" id="attRemarks" maxlength="255" placeholder="Optional note">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Save punch</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="attRuleModal" tabindex="-1" aria-labelledby="attRuleTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('attendance.rule') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="attRuleTitle">Attendance rules</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body att-modal-body">
                        <p class="att-modal-help">Rules apply when you calculate the day. Grace covers late arrival; half/full day thresholds use worked minutes.</p>
                        <div class="att-rule-grid">
                            <div>
                                <label class="form-label">Grace minutes *</label>
                                <input class="in w-100" type="number" name="grace_minutes" min="0" max="240" value="{{ old('grace_minutes', $rule->grace_minutes) }}" required>
                            </div>
                            <div>
                                <label class="form-label">Half day below (min)</label>
                                <input class="in w-100" type="number" name="half_day_minutes" min="1" max="1440" value="{{ old('half_day_minutes', $rule->half_day_minutes) }}" placeholder="Optional">
                            </div>
                            <div>
                                <label class="form-label">Full day minutes</label>
                                <input class="in w-100" type="number" name="full_day_minutes" min="1" max="1440" value="{{ old('full_day_minutes', $rule->full_day_minutes) }}" placeholder="Optional">
                            </div>
                        </div>
                        <label class="att-check">
                            <input type="checkbox" name="mark_absent_without_punch" value="1" @checked(old('mark_absent_without_punch', $rule->mark_absent_without_punch))>
                            <span>Mark absent when there is no punch</span>
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Save rules</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function ($) {
    const form = $('#attendanceFilters');
    if (!form.length) return;

    let timer = null;
    form.on('change', '[data-filter]', function () {
        form.trigger('submit');
    });
    form.on('input', 'input[name="q"]', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.trigger('submit'); }, 350);
    });

    const punchModal = document.getElementById('attPunchModal');
    if (punchModal) {
        const employee = document.getElementById('attEmployee');
        const employeeHidden = document.getElementById('attEmployeeHidden');

        function syncEmployee() {
            employeeHidden.value = employee.value || '';
        }

        employee.addEventListener('change', syncEmployee);

        punchModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            const title = document.getElementById('attPunchTitle');
            const punchIn = document.getElementById('attPunchIn');
            const punchOut = document.getElementById('attPunchOut');
            const remarks = document.getElementById('attRemarks');
            const mode = btn && btn.getAttribute('data-mode');

            if (mode === 'edit' && btn) {
                title.textContent = 'Edit punch';
                employee.value = btn.getAttribute('data-employee-id') || '';
                punchIn.value = btn.getAttribute('data-punch-in') || '';
                punchOut.value = btn.getAttribute('data-punch-out') || '';
                remarks.value = btn.getAttribute('data-remarks') || '';
                employee.disabled = true;
            } else {
                title.textContent = 'Add punch';
                employee.disabled = false;
                employee.value = '';
                punchIn.value = '';
                punchOut.value = '';
                remarks.value = '';
            }
            syncEmployee();
        });

        $('#attPunchForm').on('submit', syncEmployee);
    }
})(jQuery);
</script>
@endpush
