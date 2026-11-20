@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    @php
        $hour = (int) now()->format('G');
        $hello = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
        $maxDept = max(1, $departmentRows->max('total') ?? 1);
    @endphp
    <div class="ph">
        <div>
            <h1>{{ $hello }}, {{ auth()->user()->name }}</h1>
            <p>{{ now()->translatedFormat('l, j F Y') }} · {{ __('People currently in your scope.') }}</p>
        </div>
    </div>
    @php
        $todayMap = collect($pulse['today'] ?? [])->keyBy('key');
        $presentToday = (int) ($todayMap['present']['total'] ?? 0);
        $absentToday = (int) ($todayMap['absent']['total'] ?? 0);
        $halfToday = (int) ($todayMap['half_day']['total'] ?? 0);
        $leaveToday = (int) ($todayMap['on_leave']['total'] ?? 0);
    @endphp
    <div class="g c6">
        <div class="card kp"><small><span class="ki"><x-i name="users" :size="14" /></span>{{ __('Total employees') }}</small><b>{{ $totalEmployees }}</b><span>{{ __('In your scope') }}</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="users" :size="14" /></span>{{ __('Active') }}</small><b>{{ $activeEmployees }}</b><span class="up">{{ __('Still employed') }}</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="minus" :size="14" /></span>{{ __('Exited') }}</small><b>{{ $exitedEmployees }}</b><span>{{ __('Closed records') }}</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="building" :size="14" /></span>{{ __('Departments') }}</small><b>{{ $departments }}</b><span>{{ __('In scope') }}</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Open exits') }}</small><b>{{ $openExits }}</b><span>{{ __('Settlement not completed') }}</span></div>
        <div class="card kp"><small><span class="ki"><x-i name="minus" :size="14" /></span>{{ __('Blocked') }}</small><b>{{ $blockedEmployees }}</b><span>{{ __('Active records on hold') }}</span></div>
        @if($pulse)
            <a class="card kp" href="{{ route('attendance.index') }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Present today') }}</small><b>{{ $presentToday }}</b><span class="up">{{ __('Clocked in') }}</span></a>
            <div class="card kp"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Absent today') }}</small><b>{{ $absentToday }}</b><span class="dn">{{ __('Working day, no punch') }}</span></div>
            <div class="card kp"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Half day') }}</small><b>{{ $halfToday }}</b><span>{{ __('Partial day') }}</span></div>
            <div class="card kp"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('On leave') }}</small><b>{{ $leaveToday }}</b><span>{{ __('Approved leave') }}</span></div>
        @endif
        @if($gatePassPending !== null)
            <a class="card kp" href="{{ route('gate-passes.index', ['status' => 'pending']) }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Gate Pass pending') }}</small><b>{{ $gatePassPending }}</b><span>{{ __('Waiting approval') }}</span></a>
            <a class="card kp" href="{{ route('gate-passes.index', ['status' => 'approved']) }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Active passes') }}</small><b>{{ $gatePassActive }}</b><span>{{ __('Still valid') }}</span></a>
        @endif
    </div>
    @if($pulse)
        @php
            $toneColor = ['ok' => 'var(--ok)', 'wn' => 'var(--wn)', 'er' => 'var(--er)', 'mu' => 'var(--mu)'];
            $donut = [];
            $cursor = 0;
            foreach ($pulse['today'] as $row) {
                $share = $pulse['active'] > 0 ? round(($row['total'] / $pulse['active']) * 100, 2) : 0;
                $donut[] = $toneColor[$row['tone']].' '.$cursor.'% '.($cursor + $share).'%';
                $cursor += $share;
            }
            $donutCss = $cursor > 0 ? implode(', ', $donut) : 'var(--bd) 0 100%';
            $weekScale = max(1, collect($pulse['week'])->max(fn ($day) => $day['present'] + $day['absent'] + $day['other']));
            $shiftTotal = max(1, $pulse['shifts']['day'] + $pulse['shifts']['night']);
        @endphp
        <div class="g c2">
            <section class="card">
                <header><h3>{{ __('Today') }}</h3><span class="mu">{{ $pulse['active'] }} {{ __('active people') }}</span></header>
                <div class="cb da-split">
                    <div class="da-donut" style="background:conic-gradient({{ $donutCss }})" role="img" aria-label="Today's attendance mix">
                        <b>{{ $presentToday }}</b><small>{{ __('Present') }}</small>
                    </div>
                    <div class="da-legend">
                        @foreach($pulse['today'] as $row)
                            <div>
                                <i class="da-swatch t-{{ $row['tone'] }}"></i>
                                <span>{{ __($row['label']) }}</span>
                                <b>{{ $row['total'] }}</b>
                                <small>{{ $pulse['active'] > 0 ? (int) round(($row['total'] / $pulse['active']) * 100) : 0 }}%</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
            <section class="card">
                <header><h3>{{ __('Last 7 days') }}</h3><span class="mu">{{ __('Saved attendance days') }}</span></header>
                <div class="cb">
                    <div class="da-cols">
                        @foreach($pulse['week'] as $day)
                            @php $stack = $day['present'] + $day['absent'] + $day['other']; @endphp
                            <div class="da-col">
                                <div class="da-stack" title="{{ $day['present'] }} present, {{ $day['absent'] }} absent">
                                    <i class="t-ok" style="height:{{ $stack > 0 ? round(($day['present'] / $weekScale) * 100) : 0 }}%"></i>
                                    <i class="t-er" style="height:{{ $stack > 0 ? round(($day['absent'] / $weekScale) * 100) : 0 }}%"></i>
                                    <i class="t-mu" style="height:{{ $stack > 0 ? round(($day['other'] / $weekScale) * 100) : 0 }}%"></i>
                                </div>
                                <b>{{ $day['present'] }}</b>
                                <small>{{ $day['label'] }}</small>
                            </div>
                        @endforeach
                    </div>
                    <div class="da-key"><i class="t-ok"></i> {{ __('Present') }} <i class="t-er"></i> {{ __('Absent') }} <i class="t-mu"></i> {{ __('Other') }}</div>
                </div>
            </section>
        </div>
        <div class="g c2">
            <section class="card">
                <header><h3>{{ __('Day and night') }}</h3><span class="mu">{{ __('Night is saved on the person. Everyone else is day.') }}</span></header>
                <div class="cb da-shift">
                    <div>
                        <div class="kv"><span>{{ __('Day') }}</span><b>{{ $pulse['shifts']['day'] }}</b></div>
                        <div class="hb"><i class="t-ok" style="width:{{ (int) round(($pulse['shifts']['day'] / $shiftTotal) * 100) }}%"></i></div>
                    </div>
                    <div>
                        <div class="kv"><span>{{ __('Night') }}</span><b>{{ $pulse['shifts']['night'] }}</b></div>
                        <div class="hb"><i class="t-wn" style="width:{{ (int) round(($pulse['shifts']['night'] / $shiftTotal) * 100) }}%"></i></div>
                    </div>
                </div>
            </section>
            <section class="card">
                <header><h3>{{ __('Seven-day numbers') }}</h3></header>
                <div class="tw">
                    <table>
                        <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Present') }}</th><th>{{ __('Absent') }}</th><th>{{ __('Other') }}</th></tr></thead>
                        <tbody>
                        @foreach($pulse['week'] as $day)
                            <tr>
                                <td>{{ $day['label'] }}</td>
                                <td>{{ $day['present'] }}</td>
                                <td>{{ $day['absent'] }}</td>
                                <td>{{ $day['other'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @endif
    <div class="g c2 x">
        <section class="card">
            <header><h3>{{ __('Recent employees') }}</h3><a class="btn sm" href="{{ route('employees.index') }}">{{ __('View all') }}</a></header>
            <div class="tw">
                <table>
                    <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Department') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse($recentEmployees as $employee)
                        @php
                            $status = $employee->employmentStatus?->name ?? '—';
                            $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
                        @endphp
                        <tr class="cl" onclick="location.href='{{ route('employees.show', $employee) }}'">
                            <td>
                                <div class="who">
                                    <x-employee-avatar :employee="$employee" :size="28" />
                                    <div><b>{{ $employee->full_name }}</b><small>{{ $employee->employee_code }}</small></div>
                                </div>
                            </td>
                            <td>{{ $employee->department?->name }}</td>
                            <td><span class="b {{ $badge }}">{{ $status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center;color:var(--mu);padding:32px">{{ __('No employees in your scope yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <header><h3>{{ __('Employees by department') }}</h3></header>
            <div class="cb da-dept">
                @forelse($departmentRows as $row)
                    <div>
                        <div class="kv" style="padding:0 0 4px"><span>{{ $row->name }}</span><b>{{ $row->total }}</b></div>
                        <div class="hb"><i style="width:{{ (int) round(($row->total / $maxDept) * 100) }}%"></i></div>
                    </div>
                @empty
                    <span style="color:var(--mu)">{{ __('No departments yet.') }}</span>
                @endforelse
            </div>
        </section>
    </div>
    <div class="g c2 x">
        <section class="card">
            <header><h3>{{ __('Recent activity') }}</h3></header>
            @forelse($recentAudits as $log)
                <div class="li">
                    <span class="av" style="--h:{{ abs(crc32($log->user?->name ?? 'System')) % 360 }};width:28px;height:28px">{{ strtoupper(mb_substr($log->user?->name ?? 'S', 0, 1)) }}</span>
                    <div class="f"><b>{{ $log->user?->name ?? 'System' }}</b> {{ str_replace('_', ' ', $log->event) }}<small>{{ $log->created_at?->diffForHumans() }}</small></div>
                </div>
            @empty
                <div class="cb" style="color:var(--mu)">{{ __('Audit activity appears here when you have permission to view it.') }}</div>
            @endforelse
        </section>
    </div>
@endsection
