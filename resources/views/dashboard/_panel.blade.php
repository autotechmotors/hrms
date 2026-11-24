@php
    $hour = (int) now()->format('G');
    $hello = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
    $maxDept = max(1, $departmentRows->max('total') ?? 1);
    $todayMap = collect($pulse['today'] ?? [])->keyBy('key');
    $presentToday = (int) ($todayMap['present']['total'] ?? 0);
    $absentToday = (int) ($todayMap['absent']['total'] ?? 0);
    $halfToday = (int) ($todayMap['half_day']['total'] ?? 0);
    $leaveToday = (int) ($todayMap['on_leave']['total'] ?? 0);
    $unmarkedToday = (int) ($todayMap['unmarked']['total'] ?? 0);
    $activeNow = (int) ($pulse['active'] ?? 0);
    $presentRate = $activeNow > 0 ? (int) round(($presentToday / $activeNow) * 100) : 0;
    $weekPresent = $pulse ? (int) collect($pulse['week'])->sum('present') : 0;
    $weekAbsent = $pulse ? (int) collect($pulse['week'])->sum('absent') : 0;
    $weekOther = $pulse ? (int) collect($pulse['week'])->sum('other') : 0;
    $rangeMarked = max(1, $weekPresent + $weekAbsent + $weekOther);
    $rangeRate = (int) round(($weekPresent / $rangeMarked) * 100);
    $bestDay = $pulse ? collect($pulse['week'])->sortByDesc('present')->first() : null;
    $span = $pulse ? count($pulse['week']) : 0;
    $endsToday = $rangeTo->isToday();
    $focusTitle = $endsToday ? __('Today') : $rangeTo->translatedFormat('j M Y');
    $presentLabel = $endsToday ? __('Present today') : __('Present');
    $chartTitle = ($span === 7 && $endsToday) ? __('Last 7 days') : $rangeFrom->translatedFormat('j M').' – '.$rangeTo->translatedFormat('j M Y');
    $tableTitle = $span === 7 ? __('Seven-day numbers') : __('Daily numbers');
    $rangeLabel = $rangeFrom->translatedFormat('j M').' – '.$rangeTo->translatedFormat('j M Y');
    $presetFrom = [
        'today' => today()->toDateString(),
        '7' => today()->subDays(6)->toDateString(),
        '30' => today()->subDays(29)->toDateString(),
    ];
@endphp
<div class="dash" id="dashPanel" data-url="{{ route('dashboard') }}">
    <div class="ph">
        <div>
            <h1>{{ $hello }}, {{ auth()->user()->name }}</h1>
            <p>
                {{ $rangeLabel }}
                @if($pulse)
                    · {{ $weekPresent }} {{ __('present') }} · {{ $rangeRate }}%
                @else
                    · {{ __('People currently in your scope.') }}
                @endif
            </p>
        </div>
        <form class="dash-range" id="dashRange" method="get" action="{{ route('dashboard') }}">
            <div class="dash-presets" role="group" aria-label="{{ __('Date range') }}">
                <a class="{{ $rangePreset === 'today' ? 'on' : '' }}" data-dash-preset data-from="{{ $presetFrom['today'] }}" data-to="{{ $presetFrom['today'] }}" href="{{ route('dashboard', ['from' => $presetFrom['today'], 'to' => $presetFrom['today']]) }}">{{ __('Today') }}</a>
                <a class="{{ $rangePreset === '7' ? 'on' : '' }}" data-dash-preset data-from="{{ $presetFrom['7'] }}" data-to="{{ $presetFrom['today'] }}" href="{{ route('dashboard', ['from' => $presetFrom['7'], 'to' => $presetFrom['today']]) }}">{{ __('7 days') }}</a>
                <a class="{{ $rangePreset === '30' ? 'on' : '' }}" data-dash-preset data-from="{{ $presetFrom['30'] }}" data-to="{{ $presetFrom['today'] }}" href="{{ route('dashboard', ['from' => $presetFrom['30'], 'to' => $presetFrom['today']]) }}">{{ __('30 days') }}</a>
            </div>
            <input type="hidden" name="from" id="dashFrom" value="{{ $rangeFrom->toDateString() }}">
            <input type="hidden" name="to" id="dashTo" value="{{ $rangeTo->toDateString() }}">
            <input id="dashDates" type="text" readonly aria-label="{{ __('Date range') }}" value="{{ $rangeFrom->toDateString() }} to {{ $rangeTo->toDateString() }}">
        </form>
    </div>

    @if($pulse && $endsToday && ($unmarkedToday > 0 || $absentToday > 0))
        <div class="dash-read">
            @if($absentToday > 0)
                <span class="b er">{{ $absentToday }} {{ __('absent') }}</span>
            @endif
            @if($unmarkedToday > 0)
                <span class="b wn">{{ $unmarkedToday }} {{ __('not marked') }}</span>
            @endif
            <span>{{ __('Open attendance to settle today before the day closes.') }}</span>
            <a href="{{ route('attendance.index') }}">{{ __('Open attendance') }}</a>
        </div>
    @endif

    @if($pulse)
        <section class="dash-block">
            <h2>{{ $rangeLabel }}</h2>
            <div class="dash-metrics cols-4">
                <div class="card kp dash-kpi tone-ok"><small><span class="ki"><x-i name="chart" :size="14" /></span>{{ __('Present') }}</small><b>{{ $weekPresent }}</b><span class="up">{{ $rangeRate }}% {{ __('of saved days') }}</span></div>
                <div class="card kp dash-kpi {{ $weekAbsent > 0 ? 'tone-er' : '' }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Absent') }}</small><b>{{ $weekAbsent }}</b><span class="{{ $weekAbsent > 0 ? 'dn' : '' }}">{{ __('In this range') }}</span></div>
                <div class="card kp dash-kpi"><small><span class="ki"><x-i name="cal" :size="14" /></span>{{ __('Other') }}</small><b>{{ $weekOther }}</b><span>{{ __('Leave, holiday, half day') }}</span></div>
                <div class="card kp dash-kpi tone-ok"><small><span class="ki"><x-i name="chart" :size="14" /></span>{{ __('Strongest day') }}</small><b>{{ (int) ($bestDay['present'] ?? 0) }}</b><span>{{ $bestDay['full'] ?? '—' }}</span></div>
            </div>
        </section>

        @php
            $rangeSlices = [
                ['label' => 'Present', 'total' => $weekPresent, 'tone' => 'ok'],
                ['label' => 'Absent', 'total' => $weekAbsent, 'tone' => 'er'],
                ['label' => 'Other', 'total' => $weekOther, 'tone' => 'mu'],
            ];
            $toneColor = ['ok' => 'var(--ok)', 'wn' => 'var(--wn)', 'er' => 'var(--er)', 'mu' => 'var(--mu)'];
            $donut = [];
            $cursor = 0;
            $shownRange = [];
            foreach ($rangeSlices as $row) {
                $share = round(($row['total'] / $rangeMarked) * 100, 2);
                if ($row['total'] > 0) {
                    $donut[] = $toneColor[$row['tone']].' '.$cursor.'% '.($cursor + $share).'%';
                    $cursor += $share;
                    $shownRange[] = $row + [
                        'width' => $share,
                        'share' => ($share > 0 && $share < 1) ? '<1' : (string) (int) round($share),
                    ];
                }
            }
            $donutCss = $cursor > 0 ? implode(', ', $donut) : 'var(--bd) 0 100%';
            $weekScale = max(1, collect($pulse['week'])->max(fn ($day) => $day['present'] + $day['absent'] + $day['other']));
            $weekMid = (int) round($weekScale / 2);
            $shiftTotal = max(1, $pulse['shifts']['day'] + $pulse['shifts']['night']);
            $dayShare = (int) round(($pulse['shifts']['day'] / $shiftTotal) * 100);
            $nightShare = (int) round(($pulse['shifts']['night'] / $shiftTotal) * 100);
            $rates = [];
            $rateCount = max(1, $span - 1);
            foreach ($pulse['week'] as $index => $day) {
                $marked = $day['present'] + $day['absent'] + $day['other'];
                $rate = $marked > 0 ? min(1, $day['present'] / $marked) : 0;
                $rates[] = [
                    'x' => $span === 1 ? 50 : round(($index / $rateCount) * 100, 2),
                    'y' => round(6 + (1 - $rate) * 26, 2),
                ];
            }
            $rateLine = collect($rates)->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
            $rateArea = '0,36 '.$rateLine.' 100,36';
        @endphp

        <section class="card dash-block">
            <header><h3>{{ $chartTitle }}</h3><span class="mu">{{ __('Each column is one day') }}</span></header>
            <div class="cb">
                <div class="da-plot">
                    <div class="da-axis" aria-hidden="true"><span>{{ $weekScale }}</span><span>{{ $weekMid }}</span><span>0</span></div>
                    <div class="da-cols {{ $span > 14 ? 'tight' : '' }}" style="--days: {{ max(1, $span) }}">
                        @foreach($pulse['week'] as $day)
                            @php
                                $stack = $day['present'] + $day['absent'] + $day['other'];
                                $showLabel = $span <= 14 || $day['show_month'] || $day['is_today'] || $loop->last;
                            @endphp
                            <div class="da-col {{ $day['is_today'] ? 'is-today' : '' }}">
                                <div class="da-stack">
                                    <i class="t-ok" style="height:{{ $stack > 0 ? round(($day['present'] / $weekScale) * 100) : 0 }}%"></i>
                                    <i class="t-er" style="height:{{ $stack > 0 ? round(($day['absent'] / $weekScale) * 100) : 0 }}%"></i>
                                    <i class="t-mu" style="height:{{ $stack > 0 ? round(($day['other'] / $weekScale) * 100) : 0 }}%"></i>
                                </div>
                                <span class="da-tip">
                                    <strong>{{ $day['full'] }}</strong>
                                    <span><i class="t-ok"></i>{{ __('Present') }} <b>{{ $day['present'] }}</b></span>
                                    <span><i class="t-er"></i>{{ __('Absent') }} <b>{{ $day['absent'] }}</b></span>
                                    <span><i class="t-mu"></i>{{ __('Other') }} <b>{{ $day['other'] }}</b></span>
                                </span>
                                <b>{{ $span > 14 ? '' : $stack }}</b>
                                <small>{{ $showLabel ? ($span > 14 ? $day['label'].' '.$day['month'] : $day['label']) : '' }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="da-key"><i class="t-ok"></i> {{ __('Present') }} <i class="t-er"></i> {{ __('Absent') }} <i class="t-mu"></i> {{ __('Other') }}</div>
                @if($span > 1)
                    <div class="da-rate">
                        <small>{{ __('Present rate') }} · {{ $rangeRate }}%</small>
                        <div class="da-rate-box">
                            <svg class="da-line" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="{{ __('Present rate') }}">
                                <polygon points="{{ $rateArea }}"></polygon>
                                <polyline points="{{ $rateLine }}"></polyline>
                            </svg>
                            <div class="da-rate-hits" style="--days: {{ max(1, $span) }}">
                                @foreach($pulse['week'] as $day)
                                    @php
                                        $marked = $day['present'] + $day['absent'] + $day['other'];
                                        $dayRate = $marked > 0 ? (int) round(($day['present'] / $marked) * 100) : 0;
                                    @endphp
                                    <div class="da-hit">
                                        <span class="da-tip">
                                            <strong>{{ $day['full'] }}</strong>
                                            <span>{{ __('Present rate') }} <b>{{ $dayRate }}%</b></span>
                                            <span>{{ $day['present'] }} / {{ $marked }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <div class="g c2">
            <section class="card">
                <header><h3>{{ __('Attendance mix') }}</h3><span class="mu">{{ $rangeLabel }}</span></header>
                <div class="cb da-split">
                    <div class="da-donut" style="background:conic-gradient({{ $donutCss }})" role="img" aria-label="{{ __('Attendance mix') }}">
                        <b>{{ $rangeRate }}%</b><small>{{ __('Present') }}</small>
                    </div>
                    <div class="da-legend">
                        <div class="da-track">
                            @foreach($shownRange as $row)
                                <span class="da-seg" style="width:{{ $row['width'] }}%">
                                    <i class="t-{{ $row['tone'] }}"></i>
                                    <span class="da-tip">
                                        <strong>{{ __($row['label']) }}</strong>
                                        <span><b>{{ $row['total'] }}</b> · {{ $row['share'] }}%</span>
                                    </span>
                                </span>
                            @endforeach
                        </div>
                        @forelse($shownRange as $row)
                            <div class="da-legend-row">
                                <i class="da-swatch t-{{ $row['tone'] }}"></i>
                                <span>{{ __($row['label']) }}</span>
                                <b>{{ $row['total'] }}</b>
                                <small>{{ $row['share'] }}%</small>
                            </div>
                        @empty
                            <div><span>{{ __('No saved attendance in this range.') }}</span></div>
                        @endforelse
                    </div>
                </div>
            </section>
            <section class="card">
                <header><h3>{{ __('Day and night') }}</h3><span class="mu">{{ __('Night is saved on the person. Everyone else is day.') }}</span></header>
                <div class="cb da-shift">
                    <div class="da-mix" role="img" aria-label="{{ __('Day') }} {{ $dayShare }}%, {{ __('Night') }} {{ $nightShare }}%">
                        <span class="da-seg" style="width:{{ $dayShare }}%">
                            <i class="t-ok"></i>
                            <span class="da-tip"><strong>{{ __('Day') }}</strong><span><b>{{ $pulse['shifts']['day'] }}</b> · {{ $dayShare }}%</span></span>
                        </span>
                        <span class="da-seg" style="width:{{ $nightShare }}%">
                            <i class="t-wn"></i>
                            <span class="da-tip"><strong>{{ __('Night') }}</strong><span><b>{{ $pulse['shifts']['night'] }}</b> · {{ $nightShare }}%</span></span>
                        </span>
                    </div>
                    <div class="da-mix-key">
                        <div><i class="da-swatch t-ok"></i><span>{{ __('Day') }}</span><b>{{ $pulse['shifts']['day'] }}</b><small>{{ $dayShare }}%</small></div>
                        <div><i class="da-swatch t-wn"></i><span>{{ __('Night') }}</span><b>{{ $pulse['shifts']['night'] }}</b><small>{{ $nightShare }}%</small></div>
                    </div>
                </div>
            </section>
        </div>
    @endif

    @if($pulse || $gatePassPending !== null)
        <section class="dash-block">
            <h2>
                @if($pulse){{ $focusTitle }}@endif
                @if($pulse && $gatePassPending !== null)<span>{{ __('Gate pass') }} {{ $rangeFrom->translatedFormat('j M') }} – {{ $rangeTo->translatedFormat('j M') }}</span>@endif
                @if(! $pulse && $gatePassPending !== null){{ __('Gate pass') }}@endif
            </h2>
            <div class="dash-metrics {{ ($pulse && $gatePassPending !== null) ? 'cols-6' : ($pulse ? 'cols-4' : 'cols-2') }}">
                @if($pulse)
                    <a class="card kp dash-kpi tone-ok" href="{{ route('attendance.index') }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ $presentLabel }}</small><b>{{ $presentToday }}</b><span class="up">{{ $presentRate }}% · {{ __('Clocked in') }}</span></a>
                    <div class="card kp dash-kpi {{ $absentToday > 0 ? 'tone-er' : '' }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ $endsToday ? __('Absent today') : __('Absent') }}</small><b>{{ $absentToday }}</b><span>{{ __('Working day, no punch') }}</span></div>
                    <div class="card kp dash-kpi {{ $halfToday > 0 ? 'tone-wn' : '' }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Half day') }}</small><b>{{ $halfToday }}</b><span>{{ __('Partial day') }}</span></div>
                    <div class="card kp dash-kpi {{ $leaveToday > 0 ? 'tone-wn' : '' }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('On leave') }}</small><b>{{ $leaveToday }}</b><span>{{ __('Approved leave') }}</span></div>
                @endif
                @if($gatePassPending !== null)
                    <a class="card kp dash-kpi {{ $gatePassPending > 0 ? 'tone-wn' : '' }}" href="{{ route('gate-passes.index', ['status' => 'pending']) }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Gate Pass pending') }}</small><b>{{ $gatePassPending }}</b><span>{{ __('Waiting approval') }}</span></a>
                    <a class="card kp dash-kpi tone-ok" href="{{ route('gate-passes.index', ['status' => 'approved']) }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Active passes') }}</small><b>{{ $gatePassActive }}</b><span>{{ __('Still valid') }}</span></a>
                @endif
            </div>
        </section>
    @endif

    <section class="dash-block">
        <h2>{{ __('People') }} <span>{{ __('Current headcount') }}</span></h2>
        <div class="dash-metrics cols-6">
            <div class="card kp dash-kpi"><small><span class="ki"><x-i name="users" :size="14" /></span>{{ __('Total employees') }}</small><b>{{ $totalEmployees }}</b><span>{{ __('In your scope') }}</span></div>
            <div class="card kp dash-kpi tone-ok"><small><span class="ki"><x-i name="users" :size="14" /></span>{{ __('Active') }}</small><b>{{ $activeEmployees }}</b><span class="up">{{ __('Still employed') }}</span></div>
            <div class="card kp dash-kpi"><small><span class="ki"><x-i name="minus" :size="14" /></span>{{ __('Exited') }}</small><b>{{ $exitedEmployees }}</b><span>{{ __('Closed records') }}</span></div>
            <div class="card kp dash-kpi"><small><span class="ki"><x-i name="building" :size="14" /></span>{{ __('Departments') }}</small><b>{{ $departments }}</b><span>{{ __('In scope') }}</span></div>
            <div class="card kp dash-kpi {{ $openExits > 0 ? 'tone-wn' : '' }}"><small><span class="ki"><x-i name="clock" :size="14" /></span>{{ __('Open exits') }}</small><b>{{ $openExits }}</b><span>{{ __('Settlement not completed') }}</span></div>
            <div class="card kp dash-kpi {{ $blockedEmployees > 0 ? 'tone-er' : '' }}"><small><span class="ki"><x-i name="minus" :size="14" /></span>{{ __('Blocked') }}</small><b>{{ $blockedEmployees }}</b><span class="{{ $blockedEmployees > 0 ? 'dn' : '' }}">{{ __('Active records on hold') }}</span></div>
        </div>
    </section>

    @if($pulse)
        <div class="g c2">
            <section class="card">
                <header><h3>{{ $tableTitle }}</h3><span class="mu">{{ $rangeLabel }}</span></header>
                <div class="tw da-days">
                    <table>
                        <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Present') }}</th><th>{{ __('Absent') }}</th><th>{{ __('Other') }}</th></tr></thead>
                        <tbody>
                        @foreach($pulse['week'] as $day)
                            <tr class="{{ $day['is_today'] ? 'is-today' : '' }}">
                                <td>{{ $day['full'] }}{{ $day['is_today'] ? ' · '.__('Today') : '' }}</td>
                                <td>{{ $day['present'] }}</td>
                                <td>{{ $day['absent'] }}</td>
                                <td>{{ $day['other'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="da-total">
                            <td>{{ __('Total') }}</td>
                            <td>{{ $weekPresent }}</td>
                            <td>{{ $weekAbsent }}</td>
                            <td>{{ $weekOther }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
            <section class="card">
                <header><h3>{{ __('Employees by department') }}</h3><span class="mu">{{ $totalEmployees }} {{ __('in scope') }}</span></header>
                <div class="cb da-dept">
                    @forelse($departmentRows as $row)
                        @php $deptShare = $totalEmployees > 0 ? (int) round(($row->total / $totalEmployees) * 100) : 0; @endphp
                        <div class="da-dept-row">
                            <div class="kv" style="padding:0 0 4px"><span>{{ $row->name }}</span><b>{{ $row->total }} <small>{{ $deptShare }}%</small></b></div>
                            <div class="hb"><i style="width:{{ (int) round(($row->total / $maxDept) * 100) }}%"></i></div>
                            <span class="da-tip"><strong>{{ $row->name }}</strong><span><b>{{ $row->total }}</b> · {{ $deptShare }}%</span></span>
                        </div>
                    @empty
                        <span style="color:var(--mu)">{{ __('No departments yet.') }}</span>
                    @endforelse
                </div>
            </section>
        </div>
    @else
        <section class="card dash-block">
            <header><h3>{{ __('Employees by department') }}</h3><span class="mu">{{ $totalEmployees }} {{ __('in scope') }}</span></header>
            <div class="cb da-dept">
                @forelse($departmentRows as $row)
                    @php $deptShare = $totalEmployees > 0 ? (int) round(($row->total / $totalEmployees) * 100) : 0; @endphp
                    <div class="da-dept-row">
                        <div class="kv" style="padding:0 0 4px"><span>{{ $row->name }}</span><b>{{ $row->total }} <small>{{ $deptShare }}%</small></b></div>
                        <div class="hb"><i style="width:{{ (int) round(($row->total / $maxDept) * 100) }}%"></i></div>
                        <span class="da-tip"><strong>{{ $row->name }}</strong><span><b>{{ $row->total }}</b> · {{ $deptShare }}%</span></span>
                    </div>
                @empty
                    <span style="color:var(--mu)">{{ __('No departments yet.') }}</span>
                @endforelse
            </div>
        </section>
    @endif

    <div class="g c2">
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
            <header><h3>{{ __('Recent activity') }}</h3><span class="mu">{{ $rangeFrom->translatedFormat('j M') }} – {{ $rangeTo->translatedFormat('j M') }}</span></header>
            @if($recentAudits->isEmpty())
                <div class="cb" style="color:var(--mu)">{{ __('Audit activity appears here when you have permission to view it.') }}</div>
            @else
                <div class="dash-feed" style="grid-template-columns:1fr">
                    @foreach($recentAudits as $log)
                        <div class="li">
                            <span class="av" style="--h:{{ abs(crc32($log->user?->name ?? 'System')) % 360 }};width:28px;height:28px">{{ strtoupper(mb_substr($log->user?->name ?? 'S', 0, 1)) }}</span>
                            <div class="f"><b>{{ $log->user?->name ?? 'System' }}</b> {{ str_replace('_', ' ', $log->event) }}<small>{{ $log->created_at?->diffForHumans() }}</small></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
