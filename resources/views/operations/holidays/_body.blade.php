@php
    $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
    $daysInMonth = $start->daysInMonth;
    $prev = $start->copy()->subMonth();
    $next = $start->copy()->addMonth();
    $todayMonth = now()->startOfMonth();
    $byDate = $holidays->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
    $today = now()->toDateString();
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $leading = $start->dayOfWeekIso - 1;
    $counts = [
        'public' => $holidays->where('kind', 'public')->count(),
        'cutoff' => $holidays->where('kind', 'cutoff')->count(),
    ];
@endphp

<div class="hc-month-bar" data-hc-year="{{ $year }}" data-hc-month="{{ $month }}">
    <a class="btn hc-month-nav" href="{{ route('holidays.index', ['year' => $prev->year, 'month' => $prev->month]) }}" aria-label="Previous month"><x-i name="back" :size="14" />{{ $prev->format('M') }}</a>
    <div class="hc-month-title">
        <b>{{ $start->format('F Y') }}</b>
        <small>{{ $daysInMonth }} days</small>
    </div>
    <a class="btn hc-month-nav" href="{{ route('holidays.index', ['year' => $next->year, 'month' => $next->month]) }}" aria-label="Next month">{{ $next->format('M') }} →</a>
    @unless($start->isSameMonth($todayMonth))
        <a class="btn hc-month-nav" href="{{ route('holidays.index', ['year' => $todayMonth->year, 'month' => $todayMonth->month]) }}">Today</a>
    @endunless
</div>

<div class="hc-stats g c2">
    <div class="card kp">
        <small><span class="hc-dot public"></span>Public</small>
        <b>{{ $counts['public'] }}</b>
        <span>Paid days for everyone</span>
    </div>
    <div class="card kp">
        <small><span class="hc-dot cutoff"></span>Cutoff</small>
        <b>{{ $counts['cutoff'] }}</b>
        <span>Cut from everyone’s salary</span>
    </div>
</div>

<div class="hc-layout">
    <section class="card hc-board">
        <header>
            <div>
                <h3>{{ $start->format('F') }}</h3>
                <p>@if($canEdit)Select a day to add a holiday.@else Holidays for your company.@endif</p>
            </div>
            <div class="hc-legend">
                <span><i class="hc-dot public"></i>Public</span>
                <span><i class="hc-dot cutoff"></i>Cutoff</span>
                <span><i class="hc-dot today"></i>Today</span>
            </div>
        </header>
        <div class="cb">
            <div class="hc-weekdays">
                @foreach($weekdays as $dayName)
                    <span>{{ $dayName }}</span>
                @endforeach
            </div>
            <div class="hc-grid hc-month-grid" data-days="{{ $daysInMonth }}">
                @for($blank = 0; $blank < $leading; $blank++)
                    <span class="hc-day is-empty"></span>
                @endfor
                @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $cursor = $start->copy()->day($day);
                        $date = $cursor->toDateString();
                        $holiday = $byDate->get($date);
                    @endphp
                    @include('operations.holidays.partials.day', [
                        'date' => $date,
                        'holiday' => $holiday,
                        'isOutside' => false,
                        'today' => $today,
                        'canEdit' => $canEdit,
                    ])
                @endfor
            </div>
        </div>
    </section>

    <aside class="hc-side">
        <section class="card ef-sec">
            <header>
                <div>
                    <h3>{{ $start->format('F') }}</h3>
                    <p>{{ $holidays->count() }} {{ $holidays->count() === 1 ? 'holiday' : 'holidays' }}</p>
                </div>
            </header>
            <div class="cb">
                <div class="hc-list">
                    @forelse($holidays as $holiday)
                        <button
                            type="button"
                            class="hc-list-item kind-{{ $holiday->kind }}"
                            data-bs-toggle="modal"
                            data-bs-target="#holidayViewModal"
                            data-name="{{ $holiday->name }}"
                            data-kind="{{ $holiday->kind }}"
                            data-kind-label="{{ $holiday->kindLabel() }}"
                            data-kind-help="{{ $holiday->kindHelp() }}"
                            data-date="{{ $holiday->holiday_date->format('D, d M Y') }}"
                            data-iso="{{ $holiday->holiday_date->toDateString() }}"
                            @if($canEdit) data-delete="{{ route('holidays.destroy', $holiday) }}" @endif
                        >
                            <span class="hc-list-date">
                                <b>{{ $holiday->holiday_date->format('d') }}</b>
                                <small>{{ $holiday->holiday_date->format('M') }}</small>
                            </span>
                            <span class="hc-list-copy">
                                <b>{{ $holiday->name }}</b>
                                <small>{{ $holiday->holiday_date->format('l') }} · {{ $holiday->kind === 'cutoff' ? 'Cutoff for everyone' : ($holiday->kind === 'public' ? 'Paid for everyone' : $holiday->kindLabel()) }}</small>
                            </span>
                        </button>
                    @empty
                        <p class="ed-empty">No holidays in {{ $start->format('F Y') }}.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </aside>
</div>

<section class="card ef-sec hc-person">
    <header>
        <div>
            <h3>{{ __('One person’s paid day') }}</h3>
            <p>Pays only that person. Everyone else still works.</p>
        </div>
    </header>
    <div class="cb">
        <div class="hc-list">
            @forelse($employeeHolidays as $row)
                <div class="hc-list-item kind-public">
                    <span class="hc-list-date">
                        <b>{{ $row->holiday_date->format('d') }}</b>
                        <small>{{ $row->holiday_date->format('M') }}</small>
                    </span>
                    <span class="hc-list-copy">
                        <b>{{ $row->name }}</b>
                        <small>{{ $row->employee?->full_name }} · {{ $row->employee?->employee_code }} · paid for this person only</small>
                    </span>
                    @if($canEdit)
                        <button class="btn sm hc-person-remove" type="button" data-url="{{ route('employee-holidays.destroy', $row) }}">{{ __('Remove') }}</button>
                    @endif
                </div>
            @empty
                <p class="ed-empty">No one has a personal paid day in {{ $start->format('F Y') }}.</p>
            @endforelse
        </div>
    </div>
</section>
