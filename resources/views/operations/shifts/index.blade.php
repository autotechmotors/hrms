@extends('layouts.app')
@section('title', 'Shifts & Rosters')
@section('content')
    @php
        $prevWeek = $start->copy()->subWeek()->toDateString();
        $nextWeek = $start->copy()->addWeek()->toDateString();
        $thisWeek = now()->startOfWeek()->toDateString();
        $queryBase = array_filter([
            'q' => $filters['q'] ?: null,
            'department_id' => $filters['department_id'] ?: null,
        ]);
        $fmt = fn ($time) => \Illuminate\Support\Str::of($time)->substr(0, 5);
        $today = now()->toDateString();
    @endphp

    <div class="ph">
        <div>
            <h1>Shifts & Rosters</h1>
            <p>Define shift templates, then assign them across the week.</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('shifts.index', $queryBase + ['week' => $prevWeek]) }}" title="Previous week">
                <x-i name="back" :size="14" />
            </a>
            <span class="sh-week-label">{{ $start->format('d M') }} – {{ $end->format('d M Y') }}</span>
            <a class="btn" href="{{ route('shifts.index', $queryBase + ['week' => $nextWeek]) }}" title="Next week">→</a>
            @unless($isThisWeek)
                <a class="btn" href="{{ route('shifts.index', $queryBase + ['week' => $thisWeek]) }}">This week</a>
            @endunless
            @if($canEdit)
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#shiftModal">
                    <x-i name="plus" :size="14" />Add shift
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div class="sh-stats g c4">
        <div class="card kp">
            <small><span class="sh-dot shifts"></span>Shift templates</small>
            <b>{{ $stats['shifts'] }}</b>
            <span>Reusable codes for the roster</span>
        </div>
        <div class="card kp">
            <small><span class="sh-dot assigned"></span>Assigned</small>
            <b>{{ $stats['assigned'] }}</b>
            <span>Shift slots this week</span>
        </div>
        <div class="card kp">
            <small><span class="sh-dot off"></span>Weekly off</small>
            <b>{{ $stats['off'] }}</b>
            <span>Marked as off day</span>
        </div>
        <div class="card kp">
            <small><span class="sh-dot open"></span>Open</small>
            <b>{{ $stats['open'] }}</b>
            <span>{{ $stats['people'] }} people × 7 days</span>
        </div>
    </div>

    <section class="card sh-templates">
        <header>
            <div>
                <h3>Shift templates</h3>
                <p>Codes used in the weekly roster below.</p>
            </div>
            @if($canEdit)
                <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#shiftModal">
                    <x-i name="plus" :size="12" />New
                </button>
            @endif
        </header>
        <div class="cb">
            @if($shifts->isEmpty())
                <p class="sh-empty">No shifts yet. Add a template to start assigning the roster.</p>
            @else
                <div class="sh-chips">
                    @foreach($shifts as $shift)
                        <article class="sh-chip">
                            <div class="sh-chip-code" title="{{ $shift->code }}">{{ $shift->code }}</div>
                            <div class="sh-chip-body">
                                <div class="sh-chip-top">
                                    <b title="{{ $shift->name }}">{{ $shift->name }}</b>
                                    @if($shift->is_flexible)
                                        <span class="b ac">Flexible</span>
                                    @endif
                                </div>
                                <div class="sh-chip-meta">
                                    <span>{{ $fmt($shift->starts_at) }} – {{ $fmt($shift->ends_at) }}</span>
                                    <span class="sh-chip-sep">·</span>
                                    <span>{{ (int) $shift->break_minutes }}m break</span>
                                </div>
                            </div>
                            @if($canEdit)
                                <form class="sh-chip-act" method="post" action="{{ route('shifts.destroy', $shift) }}" data-confirm="Remove {{ $shift->name }}?">
                                    @csrf @method('delete')
                                    <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                                    @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                                    @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                                    <button class="ib er" type="submit" title="Remove"><x-i name="trash" :size="14" /></button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="card sh" id="shiftRoster">
        <form class="tbar sh-bar" method="get" action="{{ route('shifts.index') }}" id="shiftFilters">
            <input type="hidden" name="week" value="{{ $start->toDateString() }}">
            <div class="sr sh-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search employee…" autocomplete="off">
            </div>
            <select class="in" name="department_id" data-filter>
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <a class="btn sh-reset" href="{{ route('shifts.index', ['week' => $start->toDateString()]) }}" title="Reset filters">
                <x-i name="reset" :size="14" />Reset
            </a>
            <div class="sp"></div>
            <span class="sh-meta">{{ $employees->count() }} {{ \Illuminate\Support\Str::plural('person', $employees->count()) }}</span>
        </form>

        @if($canEdit && $shifts->isNotEmpty())
            <form class="sh-bulk" method="post" action="{{ route('shifts.roster.bulk') }}" id="shiftBulkForm">
                @csrf
                <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                <div class="sh-bulk-copy">
                    <b>Fill week</b>
                    <span>Set the same shift for every day in one go.</span>
                </div>
                <select class="in sh-bulk-shift" name="shift_id" id="bulkShiftId" data-native="1">
                    <option value="">Select shift…</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shift->code }} — {{ $shift->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="mode" id="bulkMode" value="shift">
                <label class="sh-bulk-check" title="Skip Saturday and Sunday">
                    <input type="checkbox" name="weekdays_only" value="1">
                    <span>Weekdays only</span>
                </label>
                <button class="btn pr" type="submit" data-bulk-mode="shift">Apply to all</button>
                <button class="btn" type="submit" data-bulk-mode="off" title="Mark all days off">All off</button>
                <button class="btn" type="submit" data-bulk-mode="clear" title="Clear all assignments">Clear</button>
            </form>
        @endif

        <div class="tw sh-board">
            <table>
                <thead>
                    <tr>
                        <th class="sh-emp-col">Employee</th>
                        @foreach($dates as $day)
                            <th class="sh-day-col {{ $day->toDateString() === $today ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}">
                                <span>{{ $day->format('D') }}</span>
                                <b>{{ $day->format('d') }}</b>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr>
                        <td class="sh-emp-col">
                            <div class="who">
                                <x-employee-avatar :employee="$employee" :size="32" />
                                <div class="sh-emp-meta">
                                    <b>{{ $employee->full_name }}</b>
                                    <small>{{ $employee->employee_code }}@if($employee->department) · {{ $employee->department->name }}@endif</small>
                                    @if($canEdit && $shifts->isNotEmpty())
                                        <form class="sh-row-fill" method="post" action="{{ route('shifts.roster.bulk') }}">
                                            @csrf
                                            <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                            <input type="hidden" name="mode" value="shift">
                                            @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                                            @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                                            <select class="in sh-row-pick" name="shift_id" data-native="1" title="Fill this person’s whole week">
                                                <option value="">Fill week…</option>
                                                @foreach($shifts as $option)
                                                    <option value="{{ $option->id }}">{{ $option->code }} all days</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                        @foreach($dates as $day)
                            @php
                                $roster = $rosters->get($employee->id.'|'.$day->toDateString())?->first();
                                $isOff = (bool) $roster?->is_off;
                                $shiftId = $isOff ? null : $roster?->shift_id;
                                $shift = $shiftId ? $shifts->firstWhere('id', $shiftId) : null;
                            @endphp
                            <td class="sh-day-col {{ $day->toDateString() === $today ? 'is-today' : '' }} {{ $isOff ? 'is-off' : ($shift ? 'is-set' : '') }}">
                                @if($canEdit)
                                    <form method="post" action="{{ route('shifts.roster') }}" class="sh-cell">
                                        @csrf
                                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                        <input type="hidden" name="work_date" value="{{ $day->toDateString() }}">
                                        <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                                        @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                                        @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                                        <select class="in sh-pick {{ $isOff ? 'is-muted' : '' }}" name="shift_id" data-native="1" onchange="this.form.querySelector('[name=is_off]').checked=false; this.form.submit()">
                                            <option value="">—</option>
                                            @foreach($shifts as $option)
                                                <option value="{{ $option->id }}" @selected((int) $shiftId === (int) $option->id)>{{ $option->code }}</option>
                                            @endforeach
                                        </select>
                                        <label class="sh-off {{ $isOff ? 'on' : '' }}" title="Mark weekly off">
                                            <input type="checkbox" name="is_off" value="1" @checked($isOff) onchange="if (this.checked) this.form.querySelector('[name=shift_id]').value=''; this.form.submit()">
                                            <span>Off</span>
                                        </label>
                                    </form>
                                @else
                                    <div class="sh-cell-view {{ $isOff ? 'off' : '' }}">
                                        @if($isOff)
                                            Off
                                        @elseif($shift)
                                            <b>{{ $shift->code }}</b>
                                            <small>{{ $fmt($shift->starts_at) }}</small>
                                        @else
                                            —
                                        @endif
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="sh-empty-row">No employees match these filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($canEdit)
        <div class="modal fade" id="shiftModal" tabindex="-1" aria-labelledby="shiftModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('shifts.store') }}">
                    @csrf
                    <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                    @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                    @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                    <div class="modal-header">
                        <h5 class="modal-title" id="shiftModalTitle">Add shift</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body sh-modal-body">
                        <div class="sh-modal-grid">
                            <div>
                                <label class="form-label">Name *</label>
                                <input class="in w-100" name="name" required value="{{ old('name') }}" placeholder="General">
                            </div>
                            <div>
                                <label class="form-label">Code *</label>
                                <input class="in w-100" name="code" required value="{{ old('code') }}" placeholder="GEN" maxlength="40">
                            </div>
                        </div>
                        <div class="sh-modal-grid">
                            <div>
                                <label class="form-label">Starts *</label>
                                <input class="in w-100" type="time" name="starts_at" required value="{{ old('starts_at', '09:00') }}">
                            </div>
                            <div>
                                <label class="form-label">Ends *</label>
                                <input class="in w-100" type="time" name="ends_at" required value="{{ old('ends_at', '18:00') }}">
                            </div>
                        </div>
                        <div class="sh-modal-grid">
                            <div>
                                <label class="form-label">Break minutes</label>
                                <input class="in w-100" type="number" name="break_minutes" min="0" max="600" value="{{ old('break_minutes', 60) }}">
                            </div>
                            <div class="sh-flex-wrap">
                                <label class="sh-check">
                                    <input type="checkbox" name="is_flexible" value="1" @checked(old('is_flexible'))>
                                    <span>Flexible timing</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Save shift</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function ($) {
    const form = $('#shiftFilters');
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

    const bulk = $('#shiftBulkForm');
    if (bulk.length) {
        bulk.on('click', '[data-bulk-mode]', function () {
            $('#bulkMode').val($(this).data('bulk-mode'));
        });
        bulk.on('submit', function (event) {
            const formEl = this;
            if (formEl.dataset.confirmed === '1') {
                return;
            }
            event.preventDefault();
            const mode = $('#bulkMode').val();
            if (mode === 'shift' && ! $('#bulkShiftId').val()) {
                if (window.toast) toast('Select a shift before applying to the week.', 'wn');
                return;
            }
            const count = {{ (int) $employees->count() }};
            const days = bulk.find('[name="weekdays_only"]').is(':checked') ? 'weekdays' : 'all 7 days';
            let message = '';
            if (mode === 'shift') {
                message = 'Apply this shift to ' + count + ' people for ' + days + '?';
            } else if (mode === 'off') {
                message = 'Mark ' + days + ' as Off for ' + count + ' people?';
            } else {
                message = 'Clear roster for ' + count + ' people (' + days + ')?';
            }
            const go = function () {
                formEl.dataset.confirmed = '1';
                formEl.submit();
            };
            if (window.askConfirm) {
                window.askConfirm(message).then(function (ok) {
                    if (ok) go();
                });
            } else {
                go();
            }
        });
    }

    $(document).on('change', '.sh-row-pick', function () {
        const select = this;
        const formEl = select.form;
        if (! formEl || ! select.value || formEl.dataset.confirmed === '1') {
            return;
        }
        select.blur();
        const code = select.options[select.selectedIndex].text;
        const message = 'Apply ' + code.replace(' all days', '') + ' to this person’s whole week?';
        const reset = function () { select.value = ''; };
        if (! window.askConfirm) {
            formEl.dataset.confirmed = '1';
            formEl.submit();
            return;
        }
        window.askConfirm(message).then(function (ok) {
            if (! ok) {
                reset();
                return;
            }
            formEl.dataset.confirmed = '1';
            formEl.submit();
        });
    });
    $('.sh-row-pick').removeAttr('onchange');
})(jQuery);
</script>
@endpush
