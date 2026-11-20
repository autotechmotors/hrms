@php
    $inr = fn ($amount): string => '₹'.number_format((float) $amount, 2);
    $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
@endphp

<form id="rpFilters" class="rp-filters" method="get" action="{{ route('reports.index') }}">
    <label>
        <span>Month</span>
        <select class="form-select" name="month" data-native>
            @foreach($months as $number => $name)
                <option value="{{ $number }}" @selected($month->month === $number)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span>Year</span>
        <input class="form-control" type="number" name="year" min="1990" max="2100" value="{{ $month->year }}">
    </label>
    <label>
        <span>Department</span>
        <select class="form-select" name="department_id" data-native>
            <option value="">All departments</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected($departmentId === (int) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>
</form>

<p class="rp-lead">{{ $month->format('F Y') }} · {{ $report['calendar_days'] }} calendar days · {{ $report['people'] }} {{ $report['people'] === 1 ? 'person' : 'people' }} · {{ $report['day_people'] }} day {{ $report['day_people'] === 1 ? 'shift' : 'shifts' }} · {{ $report['night_people'] }} night {{ $report['night_people'] === 1 ? 'shift' : 'shifts' }}@if($report['public_holidays'] > 0) · {{ $report['public_holidays'] }} public {{ $report['public_holidays'] === 1 ? 'holiday' : 'holidays' }}@endif @if($report['night_sundays'] > 0) · {{ $report['night_sundays'] }} Sundays off, paid @endif</p>

<div class="g c4 rp-stats">
    <div class="card kp"><small>Present days</small><b>{{ number_format($report['present']) }}</b><span>Clock window completed or partly worked</span></div>
    <div class="card kp"><small>Absent days</small><b>{{ number_format($report['absent']) }}</b><span>No punch on a working day</span></div>
    <div class="card kp"><small>Paid holidays</small><b>{{ number_format($report['holidays']) }}</b><span>Company holidays and one person’s paid day</span></div>
    <div class="card kp"><small>Salary cutoff</small><b>{{ $inr($report['cutoff']) }}</b><span>Late, left early, absent, and unpaid leave</span></div>
    <div class="card kp"><small>Late</small><b>{{ number_format($report['late']) }}</b><span>Minutes after the window start and grace</span></div>
    <div class="card kp"><small>Left early</small><b>{{ number_format($report['early']) }}</b><span>Minutes before the window end</span></div>
    <div class="card kp"><small>Paid leave</small><b>{{ number_format($report['paid_leave']) }}</b><span>Approved paid leave inside this month</span></div>
    <div class="card kp"><small>Unpaid leave</small><b>{{ number_format($report['unpaid_leave']) }}</b><span>Approved unpaid leave. Pays nothing</span></div>
</div>

<div class="rp-split">
    <section class="card ef-sec">
        <header>
            <div>
                <h3>{{ __('By department') }}</h3>
                <p>Person-days and cutoff for {{ $month->format('F') }}.</p>
            </div>
        </header>
        <div class="cb">
            <div class="tw">
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th>{{ __('Department') }}</th>
                        <th>{{ __('People') }}</th>
                        <th>{{ __('Present') }}</th>
                        <th>{{ __('Absent') }}</th>
                        <th>{{ __('Late') }}</th>
                        <th>{{ __('Early') }}</th>
                        <th>{{ __('Cutoff') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($report['departments'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['people'] }}</td>
                            <td>{{ $row['present'] }}</td>
                            <td>{{ $row['absent'] }}</td>
                            <td>{{ number_format($row['late']) }}</td>
                            <td>{{ number_format($row['early']) }}</td>
                            <td>{{ $inr($row['cutoff']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="rp-empty">No active people in this view.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card ef-sec">
        <header>
            <div>
                <h3>{{ __('Needs a look') }}</h3>
                <p>The largest salary cuts this month. Up to 20 people.</p>
            </div>
        </header>
        <div class="cb">
            <div class="tw">
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th>{{ __('Person') }}</th>
                        <th>{{ __('Shift') }}</th>
                        <th>{{ __('Absent') }}</th>
                        <th>{{ __('Late') }}</th>
                        <th>{{ __('Early') }}</th>
                        <th>{{ __('Cutoff') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($report['attention'] as $row)
                        <tr>
                            <td>
                                <b>{{ $row['name'] }}</b>
                                <small>{{ $row['code'] }} · {{ $row['department'] }}</small>
                            </td>
                            <td>{{ $row['shift'] }}</td>
                            <td>{{ $row['absent'] }}</td>
                            <td>{{ number_format($row['late']) }}</td>
                            <td>{{ number_format($row['early']) }}</td>
                            <td>{{ $inr($row['cutoff']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="rp-empty">No late, early, or absent cuts this month.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<div class="rp-split">
    <section class="card ef-sec">
        <header>
            <div>
                <h3>{{ __('Approved leave') }}</h3>
                <p>Days that fall inside {{ $month->format('F Y') }}.</p>
            </div>
        </header>
        <div class="cb">
            <div class="tw">
                <table class="rp-table">
                    <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Days') }}</th></tr></thead>
                    <tbody>
                    @forelse($report['leave'] as $name => $days)
                        <tr><td>{{ $name }}</td><td>{{ number_format($days) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="rp-empty">No approved leave in this month.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

</div>
