@php
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
    $formatTime = fn ($time) => \App\Support\Clock::time($time);
    $formatInput = fn ($time) => \App\Support\Clock::input($time);
@endphp
<div id="attendanceResults" data-live-list="attendance" data-page="{{ $employees->currentPage() }}" data-last="{{ $employees->lastPage() }}" data-total="{{ $employees->total() }}" data-working="{{ $stats['working'] }}" data-late="{{ $stats['late'] }}" data-half="{{ $stats['half_day'] }}" data-absent="{{ $stats['absent'] }}" data-leave="{{ $stats['on_leave'] }}" data-holiday="{{ $stats['holiday'] }}" data-off="{{ $stats['weekly_off'] }}" data-pending="{{ $stats['pending'] }}" data-unmarked="{{ $stats['unmarked'] }}">
        <div class="att-cards" data-list-rows>
            @forelse($employees as $employee)
                @php
                    $day = $days->get($employee->id);
                    $statusKey = $day?->status ?? 'pending';
                    if ($day?->punch_in && $statusKey === 'absent') {
                        $statusKey = 'present';
                    }
                    $badge = $statusClass[$statusKey] ?? '';
                    $label = $statusLabels[$statusKey] ?? str_replace('_', ' ', $statusKey);
                    $rows = $punches->get($employee->id, collect());
                    $inPunch = $rows->firstWhere('direction', 'in') ?? $rows->first();
                    $outPunch = $rows->where('direction', 'out')->last();
                    $portrait = $portraits->get($employee->id);
                    $cards = $gates[$employee->id] ?? ($gates->get($employee->id) ?? []);
                    $outMinute = $day?->punch_out ? \Carbon\Carbon::parse($day->punch_out)->format('H:i') : '';
                    $outShown = $formatTime($day?->punch_out);
                    foreach ($cards as $card) {
                        if (! empty($card['early'])) {
                            continue;
                        }
                        if ($outMinute !== '' && ($card['out_minute'] ?? '') === $outMinute) {
                            $outShown = '—';
                        }
                    }
                @endphp
                <article class="att-card {{ $statusKey === 'absent' ? 'att-row-absent' : ($statusKey === 'pending' ? 'att-row-pending' : '') }}" data-employee="{{ $employee->id }}">
                    <div class="who">
                        @if($portrait)
                            <button type="button" class="att-zoom" data-photo="{{ $portrait }}" title="View photo">
                                <img class="att-list-photo js-portrait" src="{{ $portrait }}" alt="{{ $employee->full_name }}">
                            </button>
                        @else
                            <x-employee-avatar :employee="$employee" :size="40" />
                        @endif
                        <div>
                            <a class="el-link" href="{{ route('attendance.show', ['employee' => $employee, 'date' => $date->toDateString()]) }}"><b>{{ $employee->full_name }}</b></a>
                            <small>{{ $employee->employee_code }}@if($employee->designation) · {{ $employee->designation->name }}@endif · {{ $employee->department?->name ?? '—' }}</small>
                        </div>
                    </div>
                    @if($employee->phone)
                        <a class="att-phone" href="tel:{{ preg_replace('/[^\d+]/', '', $employee->phone) }}">{{ $employee->phone }}</a>
                    @else
                        <span class="att-phone">—</span>
                    @endif
                    <div class="att-card-times">
                        <div class="att-time js-in">
                            <small>In</small>
                            <span class="js-clock">{{ $formatTime($day?->punch_in) }}</span>
                            @if($inPunch && (int) $inPunch->has_image === 1)
                                <button type="button" class="att-zoom" data-photo="{{ route('attendance.punch.photo', $inPunch) }}" title="View in photo"><img class="att-shot" src="{{ route('attendance.punch.photo', $inPunch) }}" alt="In photo"></button>
                            @endif
                        </div>
                        <div class="att-time js-out">
                            <small>Out</small>
                            <span class="js-clock">{{ $outShown }}</span>
                            @if($outPunch && (int) $outPunch->has_image === 1)
                                <button type="button" class="att-zoom" data-photo="{{ route('attendance.punch.photo', $outPunch) }}" title="View out photo"><img class="att-shot" src="{{ route('attendance.punch.photo', $outPunch) }}" alt="Out photo"></button>
                            @endif
                        </div>
                        <div>
                            <small>Worked</small>
                            <b class="js-worked">{{ $formatMinutes($day?->minutes_worked) }}</b>
                        </div>
                        <div class="js-gate">
                            <small>Gate pass</small>
                            @include('operations.attendance._gate_button', ['passes' => $cards])
                        </div>
                        <div>
                            <span class="b {{ $badge }} js-status">{{ $label }}</span>
                            @if($day?->punch_in || $day?->punch_out)
                                <span class="att-mark js-mark {{ $day->source === 'manual' ? 'is-m' : 'is-a' }}" title="{{ $day->source === 'manual' ? 'Manual' : 'Machine' }}">{{ $day->source === 'manual' ? 'M' : 'A' }}</span>
                            @endif
                        </div>
                    </div>
                    @if($canEdit)
                        <button
                            class="btn sm"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#attPunchModal"
                            data-mode="edit"
                            data-employee-id="{{ $employee->id }}"
                            data-punch-in="{{ $formatInput($day?->punch_in) }}"
                            data-punch-out="{{ $formatInput($day?->punch_out) }}"
                            data-remarks="{{ $day?->remarks }}"
                        >{{ __('Edit') }}</button>
                    @endif
                </article>
            @empty
                <p class="att-empty" data-list-empty>No employees match this day and filters.</p>
            @endforelse
        </div>

        <div class="tw att-table">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Department') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('In') }}</th>
                        <th>{{ __('Out') }}</th>
                        <th>{{ __('Worked') }}</th>
                        <th>{{ __('Status') }}</th>
                        @if($canEdit)<th></th>@endif
                    </tr>
                </thead>
                <tbody data-list-rows>
                @forelse($employees as $employee)
                    @php
                        $day = $days->get($employee->id);
                        $statusKey = $day?->status ?? 'pending';
                        if ($day?->punch_in && $statusKey === 'absent') {
                            $statusKey = 'present';
                        }
                        $badge = $statusClass[$statusKey] ?? '';
                        $label = $statusLabels[$statusKey] ?? str_replace('_', ' ', $statusKey);
                    @endphp
                    <tr class="{{ $statusKey === 'absent' ? 'att-row-absent' : ($statusKey === 'pending' ? 'att-row-pending' : '') }}" data-employee="{{ $employee->id }}" data-record="{{ route('attendance.show', ['employee' => $employee, 'date' => $date->toDateString()]) }}">
                        <td>
                            <div class="who">
                                @php $portrait = $portraits->get($employee->id); @endphp
                                @if($portrait)
                                    <button type="button" class="att-zoom" data-photo="{{ $portrait }}" title="View photo">
                                        <img class="att-list-photo js-portrait" src="{{ $portrait }}" alt="{{ $employee->full_name }}">
                                    </button>
                                @else
                                    <x-employee-avatar :employee="$employee" :size="40" />
                                @endif
                                <div>
                                    <a class="el-link" href="{{ route('attendance.show', ['employee' => $employee, 'date' => $date->toDateString()]) }}"><b>{{ $employee->full_name }}</b></a>
                                    <small>{{ $employee->employee_code }}@if($employee->designation) · {{ $employee->designation->name }}@endif</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td>
                            @if($employee->phone)
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $employee->phone) }}">{{ $employee->phone }}</a>
                            @else
                                —
                            @endif
                        </td>
                        @php
                            $rows = $punches->get($employee->id, collect());
                            $inPunch = $rows->firstWhere('direction', 'in') ?? $rows->first();
                            $outPunch = $rows->where('direction', 'out')->last();
                            $cards = $gates[$employee->id] ?? ($gates->get($employee->id) ?? []);
                            $outMinute = $day?->punch_out ? \Carbon\Carbon::parse($day->punch_out)->format('H:i') : '';
                            $outShown = $formatTime($day?->punch_out);
                            foreach ($cards as $card) {
                                if (! empty($card['early'])) {
                                    continue;
                                }
                                if ($outMinute !== '' && ($card['out_minute'] ?? '') === $outMinute) {
                                    $outShown = '—';
                                }
                            }
                        @endphp
                        <td class="att-time js-in">
                            <span class="js-clock">{{ $formatTime($day?->punch_in) }}</span>
                            @if($inPunch && (int) $inPunch->has_image === 1)
                                <button type="button" class="att-zoom" data-photo="{{ route('attendance.punch.photo', $inPunch) }}" title="View in photo"><img class="att-shot" src="{{ route('attendance.punch.photo', $inPunch) }}" alt="In photo"></button>
                            @endif
                        </td>
                        <td class="att-time js-out">
                            <span class="js-clock">{{ $outShown }}</span>
                            @if($outPunch && (int) $outPunch->has_image === 1)
                                <button type="button" class="att-zoom" data-photo="{{ route('attendance.punch.photo', $outPunch) }}" title="View out photo"><img class="att-shot" src="{{ route('attendance.punch.photo', $outPunch) }}" alt="Out photo"></button>
                            @endif
                        </td>
                        <td class="att-mins js-worked">{{ $formatMinutes($day?->minutes_worked) }}</td>
                        <td>
                            <span class="js-gate">@include('operations.attendance._gate_button', ['passes' => $cards])</span>
                            <span class="b {{ $badge }} js-status">{{ $label }}</span>
                            @if($day?->punch_in || $day?->punch_out)
                                <span class="att-mark js-mark {{ $day->source === 'manual' ? 'is-m' : 'is-a' }}" title="{{ $day->source === 'manual' ? 'Manual' : 'Machine' }}">{{ $day->source === 'manual' ? 'M' : 'A' }}</span>
                            @endif
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
                                    data-punch-in="{{ $formatInput($day?->punch_in) }}"
                                    data-punch-out="{{ $formatInput($day?->punch_out) }}"
                                    data-remarks="{{ $day?->remarks }}"
                                >
                                    <x-i name="pen" :size="14" />
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr data-list-empty>
                        <td colspan="{{ $canEdit ? 8 : 7 }}" class="att-empty">
                            No employees match this day and filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.list-footer', ['paginator' => $employees])
</div>
