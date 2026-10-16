@extends('layouts.app')
@section('title', 'Advances & Deductions')
@section('content')
    <div class="ph">
        <div>
            <h1>Advances & Deductions</h1>
            <p>EMI is only taken when payroll for that month is approved. Closing an advance stops further recovery.</p>
        </div>
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <form method="post" action="{{ route('advances.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                @csrf
                <label>Employee<br>
                    <select class="in" name="employee_id" required>
                        <option value="">Select</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Title<br><input class="in" name="title" required></label>
                <label>Amount<br><input class="in" type="number" step="0.01" min="1" name="amount" required></label>
                <label>Monthly EMI<br><input class="in" type="number" step="0.01" min="0" name="emi" required></label>
                <label>Starts<br><input class="in" type="date" name="starts_on" required></label>
                <button class="btn pr" type="submit">Add advance</button>
            </form>
        </section>
    @endif
    <section class="card">
        <div class="tw">
            <table>
                <thead><tr><th>Employee</th><th>Title</th><th>Amount</th><th>EMI</th><th>Balance</th><th>From</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($advances as $advance)
                    <tr>
                        <td>{{ $advance->employee?->full_name }}</td>
                        <td>{{ $advance->title }}</td>
                        <td>₹{{ number_format((float) $advance->amount, 2) }}</td>
                        <td>₹{{ number_format((float) $advance->emi, 2) }}</td>
                        <td>₹{{ number_format((float) $advance->balance, 2) }}</td>
                        <td>{{ $advance->starts_on->format('d M Y') }}</td>
                        <td><span class="b {{ $advance->status === 'active' ? 'wn' : 'ok' }}">{{ $advance->status }}</span></td>
                        <td>
                            @if($canEdit && $advance->status === 'active')
                                <form method="post" action="{{ route('advances.close', $advance) }}" data-confirm="Close this advance?">
                                    @csrf
                                    <button class="btn" type="submit">Close</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--mu);padding:32px">No advances</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pgn"><span></span><div>{{ $advances->links() }}</div></div>
    </section>
@endsection
