@extends('layouts.app')
@section('title', 'Shifts & Rosters')
@section('content')
    <div class="ph">
        <div>
            <h1>Shifts & Rosters</h1>
            <p>Create the shifts you use, then assign them for the week.</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('shifts.index', ['week' => $start->copy()->subWeek()->toDateString()]) }}">Previous</a>
            <a class="btn" href="{{ route('shifts.index', ['week' => $start->copy()->addWeek()->toDateString()]) }}">Next</a>
        </div>
    </div>
    @include('operations.errors')
    @if($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>New shift</h3>
            <form method="post" action="{{ route('shifts.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Name<br><input class="in" name="name" required value="{{ old('name') }}"></label>
                <label>Code<br><input class="in" name="code" required value="{{ old('code') }}"></label>
                <label>Starts<br><input class="in" type="time" name="starts_at" required value="{{ old('starts_at') }}"></label>
                <label>Ends<br><input class="in" type="time" name="ends_at" required value="{{ old('ends_at') }}"></label>
                <label>Break minutes<br><input class="in" type="number" name="break_minutes" min="0" value="{{ old('break_minutes', 0) }}"></label>
                <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="is_flexible" value="1"> Flexible</label>
                <button class="btn pr" type="submit">Add shift</button>
            </form>
        </section>
    @endif
    <section class="card" style="margin-bottom:16px">
        <header>Shifts</header>
        <div class="tw">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Time</th><th>Break</th><th></th></tr></thead>
                <tbody>
                @forelse($shifts as $shift)
                    <tr>
                        <td>{{ $shift->code }}</td>
                        <td>{{ $shift->name }} @if($shift->is_flexible)<span class="b">Flexible</span>@endif</td>
                        <td>{{ \Illuminate\Support\Str::of($shift->starts_at)->substr(0, 5) }} – {{ \Illuminate\Support\Str::of($shift->ends_at)->substr(0, 5) }}</td>
                        <td>{{ $shift->break_minutes }} min</td>
                        <td>
                            @if($canEdit)
                                <form method="post" action="{{ route('shifts.destroy', $shift) }}" data-confirm="Remove {{ $shift->name }}?">
                                    @csrf @method('delete')
                                    <button class="btn" type="submit">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--mu);padding:32px">No shifts yet. Add the ones this company uses.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <header>Week of {{ $start->format('d M Y') }}</header>
        <div class="tw">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        @foreach($dates as $day)
                            <th>{{ $day->format('D d') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr>
                        <td><b>{{ $employee->full_name }}</b><br><small style="color:var(--mu)">{{ $employee->employee_code }}</small></td>
                        @foreach($dates as $day)
                            @php $roster = $rosters->get($employee->id.'|'.$day->toDateString())?->first(); @endphp
                            <td>
                                @if($canEdit)
                                    <form method="post" action="{{ route('shifts.roster') }}">
                                        @csrf
                                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                        <input type="hidden" name="work_date" value="{{ $day->toDateString() }}">
                                        <select class="in" name="shift_id" data-native="1" onchange="this.form.submit()">
                                            <option value="">—</option>
                                            @foreach($shifts as $shift)
                                                <option value="{{ $shift->id }}" @selected($roster && ! $roster->is_off && (int) $roster->shift_id === (int) $shift->id)>{{ $shift->code }}</option>
                                            @endforeach
                                        </select>
                                        <label style="display:block;font-size:12px;color:var(--mu)"><input type="checkbox" name="is_off" value="1" @checked($roster?->is_off) onchange="this.form.submit()"> Off</label>
                                    </form>
                                @else
                                    {{ $roster?->is_off ? 'Off' : ($shifts->firstWhere('id', $roster?->shift_id)?->code ?? '—') }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--mu);padding:32px">No active employees</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
