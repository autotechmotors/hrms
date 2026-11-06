@php
    $fmt = fn ($time) => \App\Support\Clock::time($time);
    $today = now()->toDateString();
    $thisWeek = now()->startOfWeek()->toDateString();
@endphp
<div
    id="shiftBoard"
    data-people="{{ $stats['people'] }}"
    data-assigned="{{ $stats['assigned'] }}"
    data-off="{{ $stats['off'] }}"
    data-open="{{ $stats['open'] }}"
    data-shifts="{{ $stats['shifts'] }}"
    data-label="{{ $start->format('d M') }} – {{ $end->format('d M Y') }}"
    data-week="{{ $start->toDateString() }}"
    data-prev="{{ $start->copy()->subWeek()->toDateString() }}"
    data-next="{{ $start->copy()->addWeek()->toDateString() }}"
    data-this="{{ $thisWeek }}"
    data-current="{{ $start->toDateString() === $thisWeek ? '1' : '0' }}"
>
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
                                    <select class="in sh-pick {{ $isOff ? 'is-muted' : '' }}" name="shift_id" data-native="1">
                                        <option value="">—</option>
                                        @foreach($shifts as $option)
                                            <option value="{{ $option->id }}" @selected((int) $shiftId === (int) $option->id)>{{ $option->code }}</option>
                                        @endforeach
                                    </select>
                                    <label class="sh-off {{ $isOff ? 'on' : '' }}" title="Mark weekly off">
                                        <input type="checkbox" name="is_off" value="1" @checked($isOff)>
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
    <div class="pgn sh-pgn">
        <span>{{ $employees->firstItem() ?? 0 }}–{{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }}</span>
        <div>{{ $employees->links() }}</div>
    </div>
</div>
