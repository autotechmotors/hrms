@extends('layouts.app')
@section('title', $sheet->title)
@section('content')
    @php
        $dates = [];
        $cursor = $sheet->starts_on->copy();
        while ($cursor->lte($sheet->ends_on)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }
        $earned = (float) $sheet->lines->sum('earned');
        $advance = (float) $sheet->lines->sum('advance');
        $deductions = (float) $sheet->lines->sum('deductions');
        $net = (float) $sheet->lines->sum('net');
    @endphp

    <div class="ph">
        <div>
            <h1>{{ $sheet->title }}</h1>
            <p>{{ $sheet->starts_on->format('d M Y') }} – {{ $sheet->ends_on->format('d M Y') }} · {{ $sheet->lines->count() }} {{ $sheet->lines->count() === 1 ? 'person' : 'people' }}</p>
        </div>
        <div class="sg-ops">
            <a class="btn" href="{{ route('salary-sheets.index') }}">History</a>
            <a class="btn" href="{{ route('salary-sheets.excel', $sheet) }}">Excel</a>
            <a class="btn pr" href="{{ route('salary-sheets.pdf', $sheet) }}">PDF</a>
        </div>
    </div>

    @include('operations.salary-sheets._decision')

    <div class="sg-stats g c4">
        <div class="card kp"><small>Earned</small><b>₹{{ number_format($earned, 2) }}</b><span>Day pay after salary cutoff</span></div>
        <div class="card kp"><small>Advances</small><b>₹{{ number_format($advance, 2) }}</b><span>Full amount, if given in these dates</span></div>
        <div class="card kp"><small>Other deductions</small><b>₹{{ number_format($deductions, 2) }}</b><span>Salary-structure deductions on the earned pay</span></div>
        <div class="card kp"><small>Net</small><b>₹{{ number_format($net, 2) }}</b><span>Earned minus advances and other deductions</span></div>
    </div>

    <p class="sg-formula">Day pay = monthly salary ÷ the real days in that month. Completing Sunday pays the same day as completing Monday. A night shift does not work Sunday: that day is paid and has no cutoff. Late and early minutes are cut at the Monday–Saturday minute rate. A missing punch on a working day cuts the whole day. A public holiday and a person’s own paid day pay the full day. Unpaid leave pays nothing. There is no overtime. An advance is taken in full when its date falls inside this range.</p>
    <p class="sg-legend"><b>P</b> present · <b>A</b> absent · <b>L</b> paid leave · <b>UL</b> unpaid leave · <b>H</b> holiday · <b>N</b> Sunday off, paid, no punch. Late and early leave are minutes against the clock window.</p>

    @foreach($sheet->lines as $line)
        @php $person = $line->employee; @endphp
        <article class="card sg-person-card">
            <header class="sg-person-head">
                <div>
                    <h3>{{ $person?->full_name }}</h3>
                    <p>{{ $person?->employee_code }} · {{ $person?->department?->name }} · {{ $person?->designation?->name ?: 'No job title' }} · Joined {{ $person?->joining_date?->format('d M Y') ?: '—' }}</p>
                </div>
                <div class="sg-net">
                    <small>Net pay</small>
                    <b>₹{{ number_format((float) $line->net, 2) }}</b>
                </div>
            </header>

            @if((float) $line->monthly_salary <= 0)
                <p class="sg-warn">No monthly salary is set for this person. Set it under Hours &amp; pay, then generate the sheet again.</p>
            @endif

            <div class="sg-facts">
                <span><small>Monthly</small><b>₹{{ number_format((float) $line->monthly_salary, 2) }}</b></span>
                <span><small>Hours / day</small><b>{{ number_format((float) $line->hours_per_day, 2) }}</b></span>
                <span><small>Day pay</small><b>₹{{ number_format((float) $line->hourly_rate, 2) }}</b></span>
                <span><small>Attended</small><b>{{ number_format($line->attended_minutes / 60, 2) }} h</b></span>
                <span><small>Salary cutoff</small><b>₹{{ number_format((float) ($line->breakdown['cutoff'] ?? 0), 2) }}</b></span>
                <span><small>Paid leave</small><b>{{ number_format((float) $line->paid_leave_days, 1) }}</b></span>
                <span><small>Unpaid leave</small><b>{{ number_format((float) $line->unpaid_leave_days, 1) }}</b></span>
                <span><small>Absence</small><b>{{ number_format((float) $line->absence_days, 1) }}</b></span>
                <span><small>Earned</small><b>₹{{ number_format((float) $line->earned, 2) }}</b></span>
                <span><small>Advance</small><b>₹{{ number_format((float) $line->advance, 2) }}</b></span>
                <span><small>Deductions</small><b>₹{{ number_format((float) $line->deductions, 2) }}</b></span>
            </div>

            @if(($line->breakdown['deductions'] ?? []) !== [] || ($line->breakdown['advances'] ?? []) !== [])
                <ul class="sg-breaks">
                    @foreach($line->breakdown['deductions'] ?? [] as $item)
                        <li>{{ $item['name'] }} <b>₹{{ number_format((float) $item['amount'], 2) }}</b></li>
                    @endforeach
                    @foreach($line->breakdown['advances'] ?? [] as $item)
                        <li>{{ $item['name'] }} <b>₹{{ number_format((float) $item['amount'], 2) }}</b></li>
                    @endforeach
                </ul>
            @endif

            @if(($line->breakdown['cuts'] ?? []) !== [])
                <div class="tw">
                    <table class="sg-cuts">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Window</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Minutes</th>
                            <th>Rupees</th>
                            <th>Reason</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($line->breakdown['cuts'] as $cut)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($cut['date'])->format('d M Y') }}</td>
                                <td>{{ $cut['window'] }}</td>
                                <td>{{ $cut['in'] ?: '—' }}</td>
                                <td>{{ $cut['out'] ?: '—' }}</td>
                                <td>{{ (int) $cut['minutes'] === 0 && ($cut['reason'] ?? '') === 'absent' ? 'Full day' : (int) $cut['minutes'] }}</td>
                                <td>₹{{ number_format((float) $cut['rupees'], 2) }}</td>
                                <td>{{ $cut['reason'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="tw sg-grid-wrap">
                <table class="sg-grid">
                    <thead>
                    <tr>
                        <th></th>
                        @foreach($dates as $date)
                            <th>{{ \Carbon\Carbon::parse($date)->format('d') }}<small>{{ \Carbon\Carbon::parse($date)->format('D') }}</small></th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(['in' => 'Check-In', 'out' => 'Check-Out', 'late' => 'Late', 'ot' => 'OT', 'early' => 'Early Leave', 'break' => 'Break', 'attended' => 'Attended', 'status' => 'Status'] as $key => $label)
                        <tr @class(['is-status' => $key === 'status'])>
                            <th>{{ $label }}</th>
                            @foreach($dates as $date)
                                @php $cell = $line->days[$date] ?? []; $value = $cell[$key] ?? null; @endphp
                                <td>{{ $value === null || $value === '' ? '—' : $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </article>
    @endforeach
    @include('operations.salary-sheets._reject')
@endsection

@push('scripts')
    @include('operations.salary-sheets._decide-script')
@endpush
