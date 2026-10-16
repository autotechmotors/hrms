@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    @php
        $hour = (int) now()->format('G');
        $hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $maxDept = max(1, $departmentRows->max('total') ?? 1);
    @endphp
    <div class="ph">
        <div>
            <h1>{{ $hello }}, {{ auth()->user()->name }}</h1>
            <p>{{ now()->format('l, j F Y') }} · People currently in your scope.</p>
        </div>
    </div>
    <div class="g c6">
        <div class="card kp"><small><span class="ki"><x-i name="users" :size="14" /></span>Total employees</small><b>{{ $totalEmployees }}</b><span>In your scope</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="users" :size="14" /></span>Active</small><b>{{ $activeEmployees }}</b><span class="up">Working</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="minus" :size="14" /></span>Exited</small><b>{{ $exitedEmployees }}</b><span>Closed records</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="building" :size="14" /></span>Departments</small><b>{{ $departments }}</b><span>In scope</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="clock" :size="14" /></span>Open exits</small><b>{{ $openExits }}</b><span>Settlement not completed</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="wallet" :size="14" /></span>Payroll</small><b>—</b><span>Starts with the payroll module</span></div>
    </div>
    <div class="g c2 x">
        <section class="card">
            <header><h3>Recent employees</h3><a class="btn sm" href="{{ route('employees.index') }}">View all</a></header>
            <div class="tw">
                <table>
                    <thead><tr><th>Employee</th><th>Department</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($recentEmployees as $employee)
                        @php
                            $status = $employee->employmentStatus?->name ?? '—';
                            $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
                            $initials = strtoupper(collect(preg_split('/\s+/', $employee->full_name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->join(''));
                        @endphp
                        <tr class="cl" onclick="location.href='{{ route('employees.show', $employee) }}'">
                            <td>
                                <div class="who">
                                    <span class="av" style="--h:{{ abs(crc32($employee->full_name)) % 360 }};width:28px;height:28px">{{ $initials }}</span>
                                    <div><b>{{ $employee->full_name }}</b><small>{{ $employee->employee_code }}</small></div>
                                </div>
                            </td>
                            <td>{{ $employee->department?->name }}</td>
                            <td><span class="b {{ $badge }}">{{ $status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center;color:var(--mu);padding:32px">No employees in your scope yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <header><h3>Employees by department</h3></header>
            <div class="cb" style="display:grid;gap:12px">
                @forelse($departmentRows as $row)
                    <div>
                        <div class="kv" style="padding:0 0 4px"><span>{{ $row->name }}</span><b>{{ $row->total }}</b></div>
                        <div class="hb"><i style="width:{{ (int) round(($row->total / $maxDept) * 100) }}%"></i></div>
                    </div>
                @empty
                    <span style="color:var(--mu)">No departments yet.</span>
                @endforelse
            </div>
        </section>
    </div>
    <div class="g c2 x">
        <section class="card">
            <header><h3>Recent activity</h3></header>
            @forelse($recentAudits as $log)
                <div class="li">
                    <span class="av" style="--h:{{ abs(crc32($log->user?->name ?? 'System')) % 360 }};width:28px;height:28px">{{ strtoupper(mb_substr($log->user?->name ?? 'S', 0, 1)) }}</span>
                    <div class="f"><b>{{ $log->user?->name ?? 'System' }}</b> {{ str_replace('_', ' ', $log->event) }}<small>{{ $log->created_at?->diffForHumans() }}</small></div>
                </div>
            @empty
                <div class="cb" style="color:var(--mu)">Audit activity appears here when you have permission to view it.</div>
            @endforelse
        </section>
    </div>
@endsection
