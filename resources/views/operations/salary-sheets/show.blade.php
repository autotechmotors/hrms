@extends('layouts.app')
@section('title', $sheet->title)
@section('content')
    @php
        $dates = [];
        $cursor = $sheet->starts_on->copy();
        while ($cursor->lte($sheet->ends_on)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }
        $earned = (float) $sheet->lines->sum('earned');
        $advance = (float) $sheet->lines->sum('advance');
        $deductions = (float) $sheet->lines->sum('deductions');
        $net = (float) $sheet->lines->sum('net');
    @endphp

    <div class="ph">
        <div>
            <h1>{{ $sheet->title }}</h1>
            <p>{{ $sheet->starts_on->format('d M Y') }} – {{ $sheet->ends_on->format('d M Y') }} · {{ $sheet->lines->count() }} {{ $sheet->lines->count() === 1 ? 'person' : 'people' }}</p>
        </div>
        <div class="sg-ops">
            <a class="btn" href="{{ route('salary-sheets.index') }}">History</a>
            <a class="btn" href="{{ route('salary-sheets.excel', $sheet) }}">Excel</a>
            <a class="btn pr" href="{{ route('salary-sheets.pdf', $sheet) }}">PDF</a>
        </div>
    </div>

    @include('operations.salary-sheets._decision')

    <div class="sg-stats g c4">
        <div class="card kp"><small>Earned</small><b>₹{{ number_format($earned, 2) }}</b><span>Day pay after salary cutoff</span></div>
        <div class="card kp"><small>Advances</small><b>₹{{ number_format($advance, 2) }}</b><span>Full amount, if given in these dates</span></div>
        <div class="card kp"><small>Other deductions</small><b>₹{{ number_format($deductions, 2) }}</b><span>Advances are the deduction</span></div>
        <div class="card kp"><small>Net</small><b>₹{{ number_format($net, 2) }}</b><span>Earned minus advances and other deductions</span></div>
    </div>

    <p class="sg-formula">Day pay = monthly salary ÷ the real days in that month. Completing Sunday pays the same day as completing Monday. A night shift does not work Sunday: that day is paid and has no cutoff. Late and early minutes are cut at the Monday–Saturday minute rate. A missing punch on a working day cuts the whole day. A public holiday and a person’s own paid day pay the full day. Unpaid leave pays nothing. Overtime after the shift end is a separate line. An advance is taken in full when its date falls inside this range. Excel and PDF next to a name download that person’s full sheet.</p>
    <p class="sg-legend"><b>P</b> present · <b>A</b> absent · <b>L</b> leave, no cutoff · <b>UL</b> leave, salary cutoff · <b>H</b> company holiday, paid · <b>N</b> Sunday off, paid, no punch. The day grid names the holiday, the leave, and any gate pass. Late and early leave are minutes against the clock window.</p>

    @foreach($sheet->lines as $line)
        @php
            $person = $line->employee;
            $computedNet = (float) ($line->breakdown['computed_net']
                ?? ((float) $line->earned - (float) $line->deductions - (float) $line->advance + (float) ($line->breakdown['overtime_pay'] ?? 0)));
            $netAdjust = $line->breakdown['net_adjustment'] ?? null;
        @endphp
        <article class="card sg-person-card" data-line="{{ $line->id }}">
            <header class="sg-person-head">
                <div class="sg-person-title">
                    <div class="sg-person-name">
                        <h3>{{ $person?->full_name }}</h3>
                        <div class="sg-person-ops">
                            <a class="btn sm" href="{{ route('salary-sheets.line.excel', [$sheet, $line]) }}">Excel</a>
                            <a class="btn sm pr" href="{{ route('salary-sheets.line.pdf', [$sheet, $line]) }}">PDF</a>
                            @if($sheet->isPending() && ($canAdjustNet ?? false))
                                <button
                                    class="btn sm sg-net-edit"
                                    type="button"
                                    data-url="{{ route('salary-sheets.line.net', [$sheet, $line]) }}"
                                    data-name="{{ $person?->full_name }}"
                                    data-net="{{ number_format((float) $line->net, 2, '.', '') }}"
                                    data-computed="{{ number_format($computedNet, 2, '.', '') }}"
                                >Change net</button>
                            @endif
                        </div>
                    </div>
                    <p>{{ $person?->employee_code }} · {{ $person?->department?->name }} · {{ $person?->designation?->name ?: 'No job title' }} · Joined {{ $person?->joining_date?->format('d M Y') ?: '—' }}</p>
                </div>
                <div class="sg-net">
                    <small>Net pay</small>
                    <b class="js-net">₹{{ number_format((float) $line->net, 2) }}</b>
                </div>
            </header>

            @if((float) $line->monthly_salary <= 0)
                <p class="sg-warn">No monthly salary is set for this person. Set it under Hours &amp; pay, then generate the sheet again.</p>
            @endif

            <div class="sg-facts">
                <span><small>Monthly</small><b>₹{{ number_format((float) $line->monthly_salary, 2) }}</b></span>
                <span><small>Hours / day</small><b>{{ number_format((float) $line->hours_per_day, 2) }}</b></span>
                <span><small>Day pay</small><b>₹{{ number_format((float) $line->hourly_rate, 2) }}</b></span>
                <span><small>Attended</small><b>{{ number_format($line->attended_minutes / 60, 2) }} h</b></span>
                <span><small>Salary cutoff</small><b>₹{{ number_format((float) ($line->breakdown['cutoff'] ?? 0), 2) }}</b></span>
                <span><small>Overtime pay</small><b>₹{{ number_format((float) ($line->breakdown['overtime_pay'] ?? 0), 2) }}</b></span>
                <span><small>Paid leave</small><b>{{ number_format((float) $line->paid_leave_days, 1) }}</b></span>
                <span><small>Unpaid leave</small><b>{{ number_format((float) $line->unpaid_leave_days, 1) }}</b></span>
                <span><small>Absence</small><b>{{ number_format((float) $line->absence_days, 1) }}</b></span>
                <span><small>Earned</small><b>₹{{ number_format((float) $line->earned, 2) }}</b></span>
                <span><small>Advance</small><b>₹{{ number_format((float) $line->advance, 2) }}</b></span>
                <span><small>Deductions</small><b>₹{{ number_format((float) $line->deductions, 2) }}</b></span>
                <span><small>Counted net</small><b class="js-computed">₹{{ number_format($computedNet, 2) }}</b></span>
            </div>

            <div class="sg-net-note js-net-note" @if(! $netAdjust) hidden @endif>
                @if($netAdjust)
                    Net changed from ₹{{ number_format((float) $netAdjust['from'], 2) }} to ₹{{ number_format((float) $netAdjust['to'], 2) }}
                    ({{ ((float) $netAdjust['delta'] >= 0 ? '+' : '') . number_format((float) $netAdjust['delta'], 2) }}).
                    Reason: {{ $netAdjust['reason'] }}
                @endif
            </div>

            @if(($line->breakdown['deductions'] ?? []) !== [] || ($line->breakdown['advances'] ?? []) !== [])
                <ul class="sg-breaks">
                    @foreach($line->breakdown['deductions'] ?? [] as $item)
                        <li>{{ $item['name'] }} <b>₹{{ number_format((float) $item['amount'], 2) }}</b></li>
                    @endforeach
                    @foreach($line->breakdown['advances'] ?? [] as $item)
                        <li>{{ $item['name'] }} <b>₹{{ number_format((float) $item['amount'], 2) }}</b></li>
                    @endforeach
                </ul>
            @endif

            @if(($line->breakdown['cuts'] ?? []) !== [])
                <div class="tw">
                    <table class="sg-cuts">
                        <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Window') }}</th>
                            <th>{{ __('In') }}</th>
                            <th>{{ __('Out') }}</th>
                            <th>{{ __('Minutes') }}</th>
                            <th>{{ __('Rupees') }}</th>
                            <th>{{ __('Reason') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($line->breakdown['cuts'] as $cut)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($cut['date'])->format('d M Y') }}</td>
                                <td>{{ $cut['window'] }}</td>
                                <td>{{ $cut['in'] ?: '—' }}</td>
                                <td>{{ $cut['out'] ?: '—' }}</td>
                                <td>{{ (int) $cut['minutes'] === 0 && ($cut['reason'] ?? '') === 'absent' ? 'Full day' : (int) $cut['minutes'] }}</td>
                                <td>₹{{ number_format((float) $cut['rupees'], 2) }}</td>
                                <td>{{ $cut['reason'] }}@if(!empty($cut['detail'])) · {{ $cut['detail'] }}@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @php $overtime = $line->breakdown['overtime'] ?? []; @endphp
            @if($overtime !== [])
                <div class="tw">
                    <table class="sg-cuts">
                        <thead>
                        <tr>
                            <th colspan="5">Overtime</th>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <th>Shift end</th>
                            <th>Out</th>
                            <th>Extra hours</th>
                            <th>Rupees</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($overtime as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                <td>{{ $row['end'] }}</td>
                                <td>{{ $row['out'] }}</td>
                                <td>{{ $row['hours'] }}</td>
                                <td>₹{{ number_format((float) $row['rupees'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="4"><b>Total</b></td>
                            <td><b>₹{{ number_format((float) ($line->breakdown['overtime_pay'] ?? 0), 2) }}</b></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="tw sg-grid-wrap">
                <table class="sg-grid">
                    <thead>
                    <tr>
                        <th></th>
                        @foreach($dates as $date)
                            <th>{{ \Carbon\Carbon::parse($date)->format('d') }}<small>{{ \Carbon\Carbon::parse($date)->format('D') }}</small></th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(['in' => 'Check-In', 'out' => 'Check-Out', 'late' => 'Late', 'ot' => 'OT', 'early' => 'Early Leave', 'break' => 'Break', 'attended' => 'Attended', 'status' => 'Status', 'holiday' => 'Company holiday', 'leave' => 'Leave', 'gate' => 'Gate pass'] as $key => $label)
                        <tr @class(['is-status' => $key === 'status'])>
                            <th>{{ $label }}</th>
                            @foreach($dates as $date)
                                @php $cell = $line->days[$date] ?? []; $value = $cell[$key] ?? null; @endphp
                                <td class="sg-day {{ $key }}">{{ $value === null || $value === '' || $value === 0 && in_array($key, ['holiday', 'leave', 'gate'], true) ? '—' : $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </article>
    @endforeach
    @include('operations.salary-sheets._reject')

    <div class="modal fade" id="sgNetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="sgNetForm">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Change net pay</h5>
                        <p class="sg-modal-sub" id="sgNetName">One person only. A reason is required.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="sg-modal-hint">Counted net: <b id="sgNetCounted">—</b>. Overtime and advances stay as counted. Only this person’s final net changes.</p>
                    <label class="d-block mb-3">
                        <span>New net pay</span>
                        <input class="in form-control" type="number" name="net" min="0" step="0.01" required>
                    </label>
                    <label class="d-block">
                        <span>Reason</span>
                        <textarea class="in form-control" name="reason" rows="3" minlength="3" maxlength="500" required placeholder="Why this net pay is different"></textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn pr">Save net pay</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('operations.salary-sheets._decide-script')
@endpush
