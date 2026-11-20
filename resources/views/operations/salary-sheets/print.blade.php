<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $sheet->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1c161a; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 0; }
        p { margin: 0 0 8px; color: #5c5660; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #e4e4ee; padding: 3px 4px; text-align: center; }
        th { background: #E10613; color: #fff; font-weight: bold; }
        .left { text-align: left; }
        .person { page-break-inside: avoid; margin-bottom: 18px; }
        .facts td { text-align: left; border: none; padding: 2px 8px 2px 0; }
        .net { font-size: 14px; font-weight: bold; }
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
    @endphp
    <h1>{{ $sheet->company?->name }} · {{ $sheet->title }}</h1>
    <p>{{ $sheet->starts_on->format('d M Y') }} to {{ $sheet->ends_on->format('d M Y') }}</p>

    <table>
        <thead>
        <tr>
            <th class="left">Code</th>
            <th class="left">Name</th>
            <th>{{ __('Attended h') }}</th>
            <th>{{ __('Leave') }}</th>
            <th>{{ __('Absence') }}</th>
            <th>{{ __('Earned') }}</th>
            <th>{{ __('Advance') }}</th>
            <th>{{ __('Deductions') }}</th>
            <th>{{ __('Net') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($sheet->lines as $line)
            <tr>
                <td class="left">{{ $line->employee?->employee_code }}</td>
                <td class="left">{{ $line->employee?->full_name }}</td>
                <td>{{ number_format($line->attended_minutes / 60, 2) }}</td>
                <td>{{ number_format((float) $line->paid_leave_days + (float) $line->unpaid_leave_days, 1) }}</td>
                <td>{{ number_format((float) $line->absence_days, 1) }}</td>
                <td>{{ number_format((float) $line->earned, 2) }}</td>
                <td>{{ number_format((float) $line->advance, 2) }}</td>
                <td>{{ number_format((float) $line->deductions, 2) }}</td>
                <td>{{ number_format((float) $line->net, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @foreach($sheet->lines as $line)
        <div class="person">
            <h2>{{ $line->employee?->employee_code }} · {{ $line->employee?->full_name }}</h2>
            <p>{{ $line->employee?->department?->name }} · {{ $line->employee?->designation?->name }} · Joined {{ $line->employee?->joining_date?->format('d M Y') ?: '—' }}</p>
            <table class="facts">
                <tr>
                    <td>Monthly ₹{{ number_format((float) $line->monthly_salary, 2) }}</td>
                    <td>Hours/day {{ number_format((float) $line->hours_per_day, 2) }}</td>
                    <td>Day pay ₹{{ number_format((float) $line->hourly_rate, 2) }}</td>
                    <td class="net">Net ₹{{ number_format((float) $line->net, 2) }}</td>
                </tr>
            </table>
            <table>
                <thead>
                <tr>
                    <th class="left"></th>
                    @foreach($dates as $date)
                        <th>{{ \Carbon\Carbon::parse($date)->format('d') }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach(['in' => 'Check-In', 'out' => 'Check-Out', 'late' => 'Late', 'ot' => 'OT', 'early' => 'Early Leave', 'break' => 'Break', 'attended' => 'Attended', 'status' => 'Status', 'holiday' => 'Company holiday', 'leave' => 'Leave', 'gate' => 'Gate pass'] as $key => $label)
                    <tr>
                        <th class="left">{{ $label }}</th>
                        @foreach($dates as $date)
                            @php $value = $line->days[$date][$key] ?? null; @endphp
                            <td>{{ $value === null || $value === '' ? '—' : $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
