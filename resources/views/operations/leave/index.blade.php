@extends('layouts.app')
@section('title', 'Leave Management')
@section('content')
    <div class="ph">
        <div>
            <h1>Leave Management</h1>
            <p>Staff add leave for employees. Employees do not apply themselves.</p>
        </div>
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Leave type</h3>
            <p style="color:var(--mu);margin:4px 0 10px">Yearly days of 0 means there is no cap. Weekly offs and holidays are skipped unless you tick them.</p>
            <form method="post" action="{{ route('leave.types.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                @csrf
                <label>Name<br><input class="in" name="name" required></label>
                <label>Code<br><input class="in" name="code" required></label>
                <label>Yearly days<br><input class="in" type="number" step="0.5" min="0" name="annual_days" value="0"></label>
                <label>Paid<br>
                    <select class="in" name="is_paid"><option value="1">Paid</option><option value="0">Unpaid</option></select>
                </label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="counts_weekly_off" value="1"> Count weekly off</label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="counts_holidays" value="1"> Count holidays</label>
                <button class="btn pr" type="submit">Add type</button>
            </form>
        </section>
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Record leave</h3>
            <form method="post" action="{{ route('leave.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Employee<br>
                    <select class="in" name="employee_id" required>
                        <option value="">Select</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Type<br>
                    <select class="in" name="leave_type_id" required>
                        <option value="">Select</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>From<br><input class="in" type="date" name="starts_on" required></label>
                <label>To<br><input class="in" type="date" name="ends_on" required></label>
                <label>Reason<br><input class="in" name="reason" required></label>
                <button class="btn pr" type="submit">Save</button>
            </form>
        </section>
    @endif
    <section class="card" style="margin-bottom:16px">
        <header>Leave types</header>
        <div class="tw">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Yearly days</th><th>Paid</th><th>Counts off</th><th>Counts holidays</th></tr></thead>
                <tbody>
                @forelse($types as $type)
                    <tr>
                        <td>{{ $type->code }}</td>
                        <td>{{ $type->name }}</td>
                        <td>{{ (float) $type->annual_days === 0.0 ? 'No cap' : $type->annual_days }}</td>
                        <td>{{ $type->is_paid ? 'Yes' : 'No' }}</td>
                        <td>{{ $type->counts_weekly_off ? 'Yes' : 'No' }}</td>
                        <td>{{ $type->counts_holidays ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--mu);padding:32px">No leave types yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <header>Requests</header>
        <div class="tw">
            <table>
                <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($requests as $row)
                    <tr>
                        <td>{{ $row->employee?->full_name }}</td>
                        <td>{{ $row->leaveType?->name }}</td>
                        <td>{{ $row->starts_on->format('d M') }} – {{ $row->ends_on->format('d M Y') }}</td>
                        <td>{{ $row->days }}</td>
                        <td><span class="b {{ $row->status === 'approved' ? 'ok' : ($row->status === 'rejected' ? 'er' : 'wn') }}">{{ $row->status }}</span></td>
                        <td>
                            @if($canEdit && $row->status === 'pending')
                                <form method="post" action="{{ route('leave.decide', $row) }}" style="display:flex;gap:6px">
                                    @csrf
                                    <button class="btn pr" name="decision" value="approved" type="submit">Approve</button>
                                    <button class="btn" name="decision" value="rejected" type="submit">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:var(--mu);padding:32px">No leave recorded</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pgn"><span></span><div>{{ $requests->links() }}</div></div>
    </section>
@endsection
