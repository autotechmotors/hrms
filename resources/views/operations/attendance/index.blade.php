@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
    <div class="ph">
        <div>
            <h1>Attendance</h1>
            <p>Punches are entered here. Status is calculated from the rule, shift, leave, and holidays.</p>
        </div>
    </div>
    @include('operations.errors')
    <section class="card" style="padding:16px;margin-bottom:16px">
        <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
            <label>Date<br><input class="in" type="date" name="date" value="{{ $date->toDateString() }}"></label>
            <button class="btn" type="submit">Show</button>
        </form>
    </section>
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Attendance rule</h3>
            <form method="post" action="{{ route('attendance.rule') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Grace minutes<br><input class="in" type="number" name="grace_minutes" min="0" value="{{ old('grace_minutes', $rule->grace_minutes) }}" required></label>
                <label>Half day below (minutes)<br><input class="in" type="number" name="half_day_minutes" min="1" value="{{ old('half_day_minutes', $rule->half_day_minutes) }}" placeholder="Optional"></label>
                <label>Full day minutes<br><input class="in" type="number" name="full_day_minutes" min="1" value="{{ old('full_day_minutes', $rule->full_day_minutes) }}" placeholder="Optional"></label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="mark_absent_without_punch" value="1" @checked(old('mark_absent_without_punch', $rule->mark_absent_without_punch))> Absent if no punch</label>
                <button class="btn pr" type="submit">Save rule</button>
            </form>
            <form method="post" action="{{ route('attendance.recalculate') }}" style="margin-top:12px">
                @csrf
                <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">
                <button class="btn" type="submit">Calculate {{ $date->format('d M Y') }}</button>
            </form>
        </section>
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Add punch</h3>
            <form method="post" action="{{ route('attendance.punch') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">
                <label>Employee<br>
                    <select class="in" name="employee_id" required>
                        <option value="">Select</option>
                        @foreach($picker as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>In<br><input class="in" type="time" name="punch_in"></label>
                <label>Out<br><input class="in" type="time" name="punch_out"></label>
                <label>Remarks<br><input class="in" name="remarks" maxlength="255"></label>
                <button class="btn pr" type="submit">Save punch</button>
            </form>
        </section>
    @endif
    <section class="card">
        <div class="tw">
            <table>
                <thead><tr><th>Employee</th><th>In</th><th>Out</th><th>Minutes</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($employees as $employee)
                    @php $day = $days->get($employee->id); @endphp
                    <tr>
                        <td><b>{{ $employee->full_name }}</b><br><small style="color:var(--mu)">{{ $employee->employee_code }} · {{ $employee->department?->name }}</small></td>
                        <td>{{ $day?->punch_in ? \Illuminate\Support\Str::of($day->punch_in)->substr(0, 5) : '—' }}</td>
                        <td>{{ $day?->punch_out ? \Illuminate\Support\Str::of($day->punch_out)->substr(0, 5) : '—' }}</td>
                        <td>{{ $day->minutes_worked ?? '—' }}</td>
                        <td>@if($day)<span class="b {{ $day->status === 'absent' ? 'er' : ($day->status === 'late' || $day->status === 'half_day' ? 'wn' : 'ok') }}">{{ str_replace('_', ' ', $day->status) }}</span>@else<span class="b">Not calculated</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No active employees</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pgn"><span>{{ $employees->total() }} employees</span><div>{{ $employees->links() }}</div></div>
    </section>
@endsection
