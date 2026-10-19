@extends('layouts.app')
@section('title', 'Reports & Analytics')
@section('content')
    <div class="ph">
        <div>
            <h1>Reports & Analytics</h1>
            <p>{{ $month->format('F Y') }} · {{ $people }} people in scope</p>
        </div>
    </div>
    <section class="card" style="padding:16px;margin-bottom:16px">
        <form method="get" style="display:flex;gap:10px;align-items:end">
            <label>Year<br><input class="in" type="number" name="year" value="{{ $month->year }}"></label>
            <label>Month<br><input class="in" type="number" name="month" min="1" max="12" value="{{ $month->month }}"></label>
            <button class="btn pr" type="submit">Show</button>
        </form>
    </section>
    <div class="g c6" style="margin-bottom:16px">
        @foreach(['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On leave', 'half_day' => 'Half day', 'holiday' => 'Holiday'] as $key => $label)
            <div class="card kp"><b>{{ $attendance[$key] ?? 0 }}</b><span>{{ $label }}</span></div>
        @endforeach
    </div>
    <section class="card" style="margin-bottom:16px">
        <header>Headcount by department</header>
        <div class="tw">
            <table>
                <thead><tr><th>Department</th><th>People</th></tr></thead>
                <tbody>
                @forelse($headcount as $department)
                    <tr><td>{{ $department->name }}</td><td>{{ $department->employees_count }}</td></tr>
                @empty
                    <tr><td colspan="2" style="text-align:center;color:var(--mu);padding:32px">No departments</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card" style="margin-bottom:16px">
        <header>Approved leave</header>
        <div class="tw">
            <table>
                <thead><tr><th>Type</th><th>Days</th></tr></thead>
                <tbody>
                @forelse($leave as $name => $days)
                    <tr><td>{{ $name }}</td><td>{{ $days }}</td></tr>
                @empty
                    <tr><td colspan="2" style="text-align:center;color:var(--mu);padding:32px">No approved leave in this month</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card" style="margin-bottom:16px">
        <header>Late arrivals</header>
        <div class="tw">
            <table>
                <thead><tr><th>Date</th><th>Employee</th><th>In</th></tr></thead>
                <tbody>
                @forelse($late as $row)
                    <tr>
                        <td>{{ $row->work_date->format('d M Y') }}</td>
                        <td>
                            @if($row->employee)
                                <div class="who">
                                    <x-employee-avatar :employee="$row->employee" :size="28" />
                                    <div><b>{{ $row->employee->full_name }}</b></div>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row->punch_in ? \Illuminate\Support\Str::of($row->punch_in)->substr(0, 5) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:var(--mu);padding:32px">No late marks</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <header>Payroll</header>
        <div style="padding:16px">
            @if($payroll)
                <p>Status <span class="b {{ $payroll->status === 'approved' ? 'ok' : 'wn' }}">{{ $payroll->status }}</span></p>
                <p>Gross ₹{{ number_format((float) $payroll->items_sum_gross, 2) }} · Deductions ₹{{ number_format((float) $payroll->items_sum_deductions, 2) }} · Net ₹{{ number_format((float) $payroll->items_sum_net, 2) }}</p>
            @else
                <p style="color:var(--mu)">No payroll run for this month.</p>
            @endif
        </div>
    </section>
@endsection
