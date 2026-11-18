@php
    $byDate = $holidays->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
    $today = now()->toDateString();
    $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $counts = [
        'public' => $holidays->where('kind', 'public')->count(),
        'restricted' => $holidays->where('kind', 'restricted')->count(),
        'optional' => $holidays->where('kind', 'optional')->count(),
    ];
@endphp

<div class="hc-stats g c3">
    <div class="card kp">
        <small><span class="hc-dot public"></span>Public</small>
        <b>{{ $counts['public'] }}</b>
        <span>Paid days for everyone</span>
    </div>
    <div class="card kp">
        <small><span class="hc-dot restricted"></span>Restricted</small>
        <b>{{ $counts['restricted'] }}</b>
        <span>On calendar, leave-type aware</span>
    </div>
    <div class="card kp">
        <small><span class="hc-dot optional"></span>Optional</small>
        <b>{{ $counts['optional'] }}</b>
        <span>On calendar, leave-type aware</span>
    </div>
</div>

<div class="hc-layout">
    <section class="card hc-board">
        <header>
            <div>
                <h3>{{ $year }} calendar</h3>
                <p>@if($canEdit)Select a day to create a holiday.@else Holidays for your company.@endif</p>
            </div>
            <div class="hc-legend">
                <span><i class="hc-dot public"></i>Public</span>
                <span><i class="hc-dot restricted"></i>Restricted</span>
                <span><i class="hc-dot optional"></i>Optional</span>
                <span><i class="hc-dot today"></i>Today</span>
            </div>
        </header>
        <div class="cb hc-year">
            @foreach($monthNames as $month => $monthName)
                @php
                    $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
                @endphp
                <div class="hc-month" data-month="{{ $month }}">
                    <div class="hc-month-head">{{ $monthName }}</div>
                    <div class="hc-weekdays">
                        @foreach($weekdays as $dayName)
                            <span>{{ $dayName }}</span>
                        @endforeach
                    </div>
                    <div class="hc-grid">
                        @php
                            $cursor = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                            $gridEnd = $start->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
                        @endphp
                        @while($cursor->lte($gridEnd))
                            @php
                                $date = $cursor->toDateString();
                                $isOutside = (int) $cursor->month !== (int) $month;
                                $holiday = $byDate->get($date);
                            @endphp
                            @include('operations.holidays.partials.day', [
                                'date' => $date,
                                'holiday' => $holiday,
                                'isOutside' => $isOutside,
                                'today' => $today,
                                'canEdit' => $canEdit,
                            ])
                            @php $cursor->addDay(); @endphp
                        @endwhile
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <aside class="hc-side">
        <section class="card ef-sec">
            <header>
                <div>
                    <h3>This year</h3>
                    <p>{{ $holidays->count() }} holidays scheduled</p>
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
                                <small>{{ $holiday->holiday_date->format('l') }} · {{ ucfirst($holiday->kind) }}</small>
                            </span>
                        </button>
                    @empty
                        <p class="ed-empty">No holidays yet for {{ $year }}. Click a date on the calendar to add one.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </aside>
</div>

<section class="card ef-sec hc-person">
    <header>
        <div>
            <h3>One person’s paid day</h3>
            <p>Sunday off for one person. Everyone else still works 08:00–13:00. This is not leave.</p>
        </div>
    </header>
    <div class="cb">
        @if($canEdit)
            <form id="hcPersonForm" class="hc-person-form" method="post" action="{{ route('employee-holidays.store') }}">
                @csrf
                <label>
                    <span>Person</span>
                    <select class="form-select" name="employee_id" data-native required>
                        <option value="">Select</option>
                        @foreach($employees as $person)
                            <option value="{{ $person->id }}">{{ $person->employee_code }} · {{ $person->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Date</span>
                    <input class="form-control" type="date" name="holiday_date" required>
                </label>
                <label>
                    <span>Name</span>
                    <input class="form-control" type="text" name="name" maxlength="120" placeholder="Sunday off" required>
                </label>
                <button class="btn pr" type="submit">Save</button>
            </form>
        @endif
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
                        <button class="btn sm hc-person-remove" type="button" data-url="{{ route('employee-holidays.destroy', $row) }}">Remove</button>
                    @endif
                </div>
            @empty
                <p class="ed-empty">No one has a personal paid day in {{ $year }}.</p>
            @endforelse
        </div>
    </div>
</section>
