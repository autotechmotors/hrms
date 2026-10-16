@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
    @php $label = \Carbon\Carbon::create($run->period_year, $run->period_month, 1)->format('F Y'); @endphp
    <div class="ph">
        <div>
            <h1>{{ $label }}</h1>
            <p>{{ $run->working_days }} working days · {{ $run->status }}</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('payroll.index') }}">All periods</a>
            @if($canProcess && $run->status !== 'approved')
                <form method="post" action="{{ route('payroll.compute', $run) }}">@csrf<button class="btn pr" type="submit">Calculate</button></form>
            @endif
            @if($canApprove && $run->status === 'computed')
                <form method="post" action="{{ route('payroll.approve', $run) }}" data-confirm="Approve {{ $label }} and deduct advance EMI?">@csrf<button class="btn pr" type="submit">Approve</button></form>
            @endif
        </div>
    </div>
    <section class="card" style="margin-bottom:16px">
        <div class="tw">
            <table>
                <thead><tr><th>Employee</th><th>Paid days</th><th>LOP</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead>
                <tbody>
                @forelse($run->items as $item)
                    <tr>
                        <td>{{ $item->employee?->full_name }}<br><small style="color:var(--mu)">{{ $item->employee?->employee_code }}</small></td>
                        <td>{{ $item->paid_days }}</td>
                        <td>{{ $item->lop_days }}</td>
                        <td>₹{{ number_format((float) $item->gross, 2) }}</td>
                        <td>₹{{ number_format((float) $item->deductions, 2) }}</td>
                        <td>₹{{ number_format((float) $item->net, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--mu);padding:32px">Calculate this period to build the register.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @if($run->status !== 'draft' && $missing->isNotEmpty())
        <section class="card">
            <header>Skipped — no salary effective this month</header>
            <div class="tw">
                <table>
                    <tbody>
                    @foreach($missing as $employee)
                        <tr><td>{{ $employee->employee_code }}</td><td>{{ $employee->full_name }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
