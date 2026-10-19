@extends('layouts.app')
@section('title', $device->name)
@section('content')
    <div class="ph">
        <div>
            <h1>{{ $device->name }}</h1>
            <p>{{ $device->place ?: 'No place' }} · {{ $device->ip_address ?: 'No IP' }} · {{ $device->status }}</p>
        </div>
        <div class="ac"><a class="btn" href="{{ route('devices.index') }}">All devices</a></div>
    </div>
    @include('operations.errors')
    @if ($canEdit)
        <section class="card" style="padding:16px;margin-bottom:16px">
            <form method="post" action="{{ route('devices.update', $device) }}" style="display:flex;gap:10px;align-items:end">
                @csrf @method('put')
                <label>Status after a manual check<br>
                    <select class="in" name="status">
                        @foreach (['unknown', 'online', 'offline'] as $status)
                            <option value="{{ $status }}" @selected($device->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn pr" type="submit">Save check</button>
            </form>
            <p style="color:var(--mu);margin-top:8px">This stores the check time only. It does not read punches from the
                terminal.</p>
        </section>
        <section class="card" style="padding:16px;margin-bottom:16px">
            <h3>Enroll employee</h3>
            <form method="post" action="{{ route('devices.enroll', $device) }}"
                style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:10px">
                @csrf
                <label>Employee<br>
                    <select class="in" name="employee_id" required>
                        <option value="">Select</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Expires<br><input class="in" type="date" name="expires_on"></label>
                <button class="btn pr" type="submit">Enroll</button>
            </form>
        </section>
    @endif
    <section class="card">
        <div class="tw">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Expires</th>
                        <th>Blocked</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($device->users as $enrollment)
                        <tr>
                            <td>
                                @if ($enrollment->employee)
                                    <div class="who">
                                        <x-employee-avatar :employee="$enrollment->employee" :size="28" />
                                        <div><b>{{ $enrollment->employee->full_name }}</b></div>
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $enrollment->expires_on?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $enrollment->is_blocked ? 'Yes' : 'No' }}</td>
                            <td>
                                @if ($canEdit)
                                    <form method="post" action="{{ route('devices.block', $enrollment) }}"
                                        style="display:inline">@csrf<button class="btn"
                                            type="submit">{{ $enrollment->is_blocked ? 'Unblock' : 'Block' }}</button>
                                    </form>
                                    <form method="post" action="{{ route('devices.enrollment.destroy', $enrollment) }}"
                                        style="display:inline" data-confirm="Remove this enrollment?">@csrf
                                        @method('delete')<button class="btn" type="submit">Remove</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--mu);padding:32px">Nobody is enrolled
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
