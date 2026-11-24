<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $sheet->title }}</title>
    <style>
        @page { margin: 7mm; size: A3 landscape; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.2px;
            color: #1c161a;
            line-height: 1.25;
        }
        .sheet { width: 100%; }
        .brand {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .brand td { border: none; padding: 0; vertical-align: middle; }
        .brand-mark {
            width: 6px;
            background: #E10613;
        }
        .brand-main {
            padding: 6px 10px 6px 12px;
            background: #f7f4f5;
            border: 1px solid #ece8ea;
            border-left: none;
        }
        .brand-main h1 {
            margin: 0;
            font-size: 13px;
            letter-spacing: -.2px;
            color: #1c161a;
        }
        .brand-main p {
            margin: 2px 0 0;
            color: #6b6570;
            font-size: 7.5px;
        }
        .brand-net {
            width: 150px;
            text-align: right;
            padding: 6px 12px;
            background: #1c161a;
            color: #fff;
            border: 1px solid #1c161a;
        }
        .brand-net small {
            display: block;
            font-size: 6.5px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255,255,255,.72);
        }
        .brand-net b {
            display: block;
            margin-top: 2px;
            font-size: 14px;
        }
        .kpis {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            table-layout: fixed;
        }
        .kpis td {
            border: 1px solid #ece8ea;
            background: #fff;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        .kpis small {
            display: block;
            color: #6b6570;
            font-size: 6px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .kpis b {
            display: block;
            margin-top: 1px;
            font-size: 8px;
            color: #1c161a;
        }
        .section {
            margin: 0 0 5px;
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #E10613;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }
        .grid th, .grid td {
            border: 1px solid #ece8ea;
            padding: 1px 1px;
            text-align: center;
            font-size: 6.2px;
            white-space: nowrap;
            overflow: hidden;
        }
        .grid thead th {
            background: #E10613;
            color: #fff;
            font-weight: 700;
            padding: 2px 1px;
        }
        .grid tbody th {
            text-align: left;
            padding-left: 4px;
            background: #faf8f9;
            font-weight: 700;
            width: 58px;
        }
        .grid .muted { color: #9a949a; }
        .split {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .split > td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .split .col-sum { width: 28%; padding-right: 6px; }
        .split .col-cuts { width: 72%; }
        .panel {
            width: 100%;
            border: 1px solid #ece8ea;
            border-collapse: collapse;
        }
        .panel th {
            background: #E10613;
            color: #fff;
            font-size: 6.5px;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: 3px 4px;
            text-align: left;
        }
        .panel td {
            border-top: 1px solid #f0ecee;
            padding: 2px 4px;
            font-size: 6.4px;
        }
        .panel .label { color: #6b6570; width: 58%; }
        .panel .value { text-align: right; font-weight: 700; }
        .cuts {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .cuts th {
            background: #E10613;
            color: #fff;
            font-size: 6px;
            letter-spacing: .04em;
            text-transform: uppercase;
            padding: 2px 3px;
            text-align: center;
        }
        .cuts th.left, .cuts td.left { text-align: left; }
        .cuts td {
            border: 1px solid #ece8ea;
            padding: 1px 3px;
            font-size: 6px;
            text-align: center;
        }
        .foot {
            margin-top: 4px;
            color: #8a848a;
            font-size: 6px;
        }
        .person + .person { page-break-before: always; }
        .roster {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .roster th, .roster td {
            border: 1px solid #ece8ea;
            padding: 3px 4px;
            font-size: 7px;
            text-align: center;
        }
        .roster th { background: #E10613; color: #fff; }
        .roster .left { text-align: left; }
    </style>
</head>
<body>
@php
    $dates = [];
    $cursor = $sheet->starts_on->copy();
    while ($cursor->lte($sheet->ends_on)) {
        $dates[] = $cursor->toDateString();
        $cursor->addDay();
    }
    $single = $sheet->lines->count() === 1;
    $hours = fn (int $minutes): string => number_format($minutes / 60, 2);
    $money = fn ($value): string => '₹'.number_format((float) $value, 2);
@endphp

@if(! $single)
    <table class="brand">
        <tr>
            <td class="brand-mark"></td>
            <td class="brand-main">
                <h1>{{ $sheet->company?->name }} · {{ $sheet->title }}</h1>
                <p>{{ $sheet->starts_on->format('d M Y') }} to {{ $sheet->ends_on->format('d M Y') }} · {{ $sheet->lines->count() }} people</p>
            </td>
        </tr>
    </table>
    <table class="roster">
        <thead>
        <tr>
            <th class="left">Code</th>
            <th class="left">Name</th>
            <th>Attended</th>
            <th>Leave</th>
            <th>Absence</th>
            <th>Cutoff</th>
            <th>Earned</th>
            <th>Advance</th>
            <th>Net</th>
        </tr>
        </thead>
        <tbody>
        @foreach($sheet->lines as $line)
            <tr>
                <td class="left">{{ $line->employee?->employee_code }}</td>
                <td class="left">{{ $line->employee?->full_name }}</td>
                <td>{{ $hours((int) $line->attended_minutes) }} h</td>
                <td>{{ number_format((float) $line->paid_leave_days + (float) $line->unpaid_leave_days, 1) }}</td>
                <td>{{ number_format((float) $line->absence_days, 1) }}</td>
                <td>{{ $money($line->breakdown['cutoff'] ?? 0) }}</td>
                <td>{{ $money($line->earned) }}</td>
                <td>{{ $money($line->advance) }}</td>
                <td>{{ $money($line->net) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@foreach($sheet->lines as $line)
    @php
        $cuts = $line->breakdown['cuts'] ?? [];
        $overtime = $line->breakdown['overtime'] ?? [];
        $person = $line->employee;
    @endphp
    <div class="person sheet">
        <table class="brand">
            <tr>
                <td class="brand-mark"></td>
                <td class="brand-main">
                    <h1>{{ $person?->full_name }}</h1>
                    <p>
                        {{ $person?->employee_code }}
                        · {{ $person?->department?->name ?: '—' }}
                        · {{ $person?->designation?->name ?: '—' }}
                        · Joined {{ $person?->joining_date?->format('d M Y') ?: '—' }}
                        · {{ $sheet->company?->name }}
                        · {{ $sheet->title }}
                        · {{ $sheet->starts_on->format('d M Y') }} – {{ $sheet->ends_on->format('d M Y') }}
                    </p>
                </td>
                <td class="brand-net">
                    <small>Net pay</small>
                    <b>{{ $money($line->net) }}</b>
                </td>
            </tr>
        </table>

        <table class="kpis">
            <tr>
                <td><small>Monthly</small><b>{{ $money($line->monthly_salary) }}</b></td>
                <td><small>Hours / day</small><b>{{ number_format((float) $line->hours_per_day, 2) }}</b></td>
                <td><small>Day pay</small><b>{{ $money($line->hourly_rate) }}</b></td>
                <td><small>Attended</small><b>{{ $hours((int) $line->attended_minutes) }} h</b></td>
                <td><small>Late</small><b>{{ $hours((int) $line->late_minutes) }} h</b></td>
                <td><small>Early</small><b>{{ $hours((int) $line->early_minutes) }} h</b></td>
                <td><small>Salary cutoff</small><b>{{ $money($line->breakdown['cutoff'] ?? 0) }}</b></td>
                <td><small>Overtime</small><b>{{ $hours((int) $line->ot_minutes) }} h</b></td>
                <td><small>OT pay</small><b>{{ $money($line->breakdown['overtime_pay'] ?? 0) }}</b></td>
                <td><small>Earned</small><b>{{ $money($line->earned) }}</b></td>
                <td><small>Advance</small><b>{{ $money($line->advance) }}</b></td>
                <td><small>Absence</small><b>{{ number_format((float) $line->absence_days, 1) }}</b></td>
            </tr>
        </table>

        <div class="section">Day register</div>
        <table class="grid">
            <thead>
            <tr>
                <th></th>
                @foreach($dates as $date)
                    @php $day = \Carbon\Carbon::parse($date); @endphp
                    <th>{{ $day->format('d') }}<br>{{ $day->format('D') }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach(['in' => 'Check-In', 'out' => 'Check-Out', 'late' => 'Late', 'ot' => 'OT', 'early' => 'Early', 'break' => 'Break', 'attended' => 'Attended', 'status' => 'Status', 'holiday' => 'Holiday', 'leave' => 'Leave', 'gate' => 'Gate'] as $key => $label)
                <tr>
                    <th>{{ $label }}</th>
                    @foreach($dates as $date)
                        @php
                            $value = $line->days[$date][$key] ?? null;
                            $empty = $value === null || $value === '' || $value === 0 || $value === '0';
                        @endphp
                        <td @class(['muted' => $empty])>{{ $empty ? '—' : $value }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="split">
            <tr>
                <td class="col-sum">
                    <div class="section">Summary</div>
                    <table class="panel">
                        <thead><tr><th colspan="2">Pay summary</th></tr></thead>
                        <tbody>
                        @foreach([
                            ['Attended', $hours((int) $line->attended_minutes).' h'],
                            ['Paid hours', $hours((int) $line->paid_minutes).' h'],
                            ['Overtime', $hours((int) $line->ot_minutes).' h'],
                            ['Late', $hours((int) $line->late_minutes).' h'],
                            ['Early leave', $hours((int) $line->early_minutes).' h'],
                            ['Break', $hours((int) $line->break_minutes).' h'],
                            ['Paid leave', number_format((float) $line->paid_leave_days, 1)],
                            ['Unpaid leave', number_format((float) $line->unpaid_leave_days, 1)],
                            ['Absence', number_format((float) $line->absence_days, 1)],
                            ['Salary cutoff', $money($line->breakdown['cutoff'] ?? 0)],
                            ['Overtime pay', $money($line->breakdown['overtime_pay'] ?? 0)],
                            ['Earned', $money($line->earned)],
                            ['Advance', $money($line->advance)],
                            ['Deductions', $money($line->deductions)],
                            ['Counted net', $money($line->breakdown['computed_net'] ?? $line->net)],
                            ['Net pay', $money($line->net)],
                        ] as $row)
                            <tr>
                                <td class="label">{{ $row[0] }}</td>
                                <td class="value">{{ $row[1] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </td>
                <td class="col-cuts">
                    <div class="section">Late entry and salary cutoff</div>
                    @php $adjust = $line->breakdown['net_adjustment'] ?? null; @endphp
                    @if(is_array($adjust) && ($adjust['reason'] ?? '') !== '')
                        <table class="panel" style="margin-bottom:5px">
                            <thead><tr><th colspan="2">Net pay change</th></tr></thead>
                            <tbody>
                            <tr><td class="label">From counted</td><td class="value">{{ $money($adjust['from'] ?? 0) }}</td></tr>
                            <tr><td class="label">To net pay</td><td class="value">{{ $money($adjust['to'] ?? $line->net) }}</td></tr>
                            <tr><td class="label">Difference</td><td class="value">{{ number_format((float) ($adjust['delta'] ?? 0), 2) }}</td></tr>
                            <tr><td class="label">Reason</td><td class="value">{{ $adjust['reason'] }}</td></tr>
                            </tbody>
                        </table>
                    @endif
                    @if($cuts === [])
                        <table class="panel"><tbody><tr><td class="label">No late entry or cutoff rows</td><td class="value">—</td></tr></tbody></table>
                    @else
                        <table class="cuts">
                            <thead>
                            <tr>
                                <th class="left">Date</th>
                                <th>Window</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>Min</th>
                                <th>Rupees</th>
                                <th class="left">Reason</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($cuts as $cut)
                                <tr>
                                    <td class="left">{{ \Carbon\Carbon::parse($cut['date'])->format('d M') }}</td>
                                    <td>{{ $cut['window'] }}</td>
                                    <td>{{ $cut['in'] ?: '—' }}</td>
                                    <td>{{ $cut['out'] ?: '—' }}</td>
                                    <td>{{ (int) $cut['minutes'] === 0 && ($cut['reason'] ?? '') === 'absent' ? 'Full' : (int) $cut['minutes'] }}</td>
                                    <td>{{ number_format((float) $cut['rupees'], 2) }}</td>
                                    <td class="left">{{ $cut['reason'] }}@if(!empty($cut['detail'])) · {{ $cut['detail'] }}@endif</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if($overtime !== [])
                        <div class="section" style="margin-top:5px">Overtime</div>
                        <table class="cuts">
                            <thead>
                            <tr>
                                <th class="left">Date</th>
                                <th>Shift end</th>
                                <th>Out</th>
                                <th>Hours</th>
                                <th>Rupees</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($overtime as $row)
                                <tr>
                                    <td class="left">{{ \Carbon\Carbon::parse($row['date'])->format('d M') }}</td>
                                    <td>{{ $row['end'] }}</td>
                                    <td>{{ $row['out'] }}</td>
                                    <td>{{ $row['hours'] }}</td>
                                    <td>{{ number_format((float) $row['rupees'], 2) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td class="left" colspan="4"><b>Total</b></td>
                                <td><b>{{ number_format((float) ($line->breakdown['overtime_pay'] ?? 0), 2) }}</b></td>
                            </tr>
                            </tbody>
                        </table>
                    @endif
                </td>
            </tr>
        </table>

        <p class="foot">P present · A absent · L leave · UL unpaid leave · H holiday · N Sunday off. Values are frozen on the saved sheet.</p>
    </div>
@endforeach
</body>
</html>
