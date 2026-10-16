@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
    <div class="ph">
        <div>
            <h1>Payroll</h1>
            <p>Open a month, calculate it, then approve. Approval reduces advance balances. It does not pay anyone.</p>
        </div>
    </div>
    @include('operations.errors')
    @if($canProcess)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <form method="post" action="{{ route('payroll.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                @csrf
                <label>Year<br><input class="in" type="number" name="period_year" value="{{ now()->year }}" required></label>
                <label>Month<br><input class="in" type="number" name="period_month" min="1" max="12" value="{{ now()->month }}" required></label>
                <button class="btn pr" type="submit">Open period</button>
            </form>
        </section>
    @endif
    <section class="card">
        <div class="tw">
            <table>
                <thead><tr><th>Period</th><th>Working days</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($runs as $run)
                    <tr class="cl" onclick="location.href='{{ route('payroll.show', $run) }}'">
                        <td>{{ \Carbon\Carbon::create($run->period_year, $run->period_month, 1)->format('F Y') }}</td>
                        <td>{{ $run->working_days }}</td>
                        <td><span class="b {{ $run->status === 'approved' ? 'ok' : 'wn' }}">{{ $run->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:var(--mu);padding:32px">No payroll periods</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
