@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
    @php
        $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
        $thisYear = (int) now()->year;
        $thisMonth = (int) now()->month;
        $inr = fn ($n): string => '₹'.number_format((float) $n, 2);
    @endphp

    <div class="ph">
        <div>
            <h1>Payroll</h1>
            <p>Open a month, calculate from attendance, then approve. Approval recovers advance EMI — it does not transfer money.</p>
        </div>
        <div class="ac">
            @if($canProcess)
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#payrollOpenModal">
                    <x-i name="plus" :size="14" />Open period
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <ol class="py-flow" aria-label="Payroll steps">
        <li>
            <span class="py-flow-num">1</span>
            <div>
                <b>Open</b>
                <small>Create the month</small>
            </div>
        </li>
        <li>
            <span class="py-flow-num">2</span>
            <div>
                <b>Calculate</b>
                <small>Attendance &amp; salary</small>
            </div>
        </li>
        <li>
            <span class="py-flow-num">3</span>
            <div>
                <b>Approve</b>
                <small>Lock &amp; recover EMI</small>
            </div>
        </li>
    </ol>

    <div class="py-stats g c4">
        <div class="card kp">
            <small><span class="py-dot periods"></span>Pay periods</small>
            <b>{{ $stats['total'] }}</b>
            <span>Opened for this company</span>
        </div>
        <div class="card kp">
            <small><span class="py-dot draft"></span>Draft</small>
            <b>{{ $stats['draft'] }}</b>
            <span>Not calculated yet</span>
        </div>
        <div class="card kp">
            <small><span class="py-dot computed"></span>Calculated</small>
            <b>{{ $stats['computed'] }}</b>
            <span>Ready to review</span>
        </div>
        <div class="card kp">
            <small><span class="py-dot approved"></span>Approved</small>
            <b>{{ $stats['approved'] }}</b>
            <span>Locked for the month</span>
        </div>
    </div>

    <section class="card py-board">
        <header>
            <div>
                <h3>Pay periods</h3>
                <p>Select a month to calculate, review, or approve.</p>
            </div>
            @if($canProcess)
                <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#payrollOpenModal">
                    <x-i name="plus" :size="12" />Open
                </button>
            @endif
        </header>
        <div class="cb">
            @if($runs->isEmpty())
                <div class="py-empty">
                    <h3>No pay periods yet</h3>
                    <p>Open {{ now()->format('F Y') }} to start payroll for this company.</p>
                    @if($canProcess)
                        <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#payrollOpenModal">
                            <x-i name="plus" :size="14" />Open {{ now()->format('F Y') }}
                        </button>
                    @endif
                </div>
            @else
                <div class="py-runs">
                    @foreach($runs as $run)
                        @php
                            $isCurrent = (int) $run->period_year === $thisYear && (int) $run->period_month === $thisMonth;
                            $employees = (int) ($run->items_count ?? 0);
                            $net = (float) ($run->items_sum_net ?? 0);
                            $gross = (float) ($run->items_sum_gross ?? 0);
                        @endphp
                        <a class="py-run is-{{ $run->status }}" href="{{ route('payroll.show', $run) }}">
                            <div class="py-run-period">
                                <span class="py-run-month">{{ $monthNames[(int) $run->period_month] ?? $run->period_month }}</span>
                                <b>{{ $run->period_year }}</b>
                                @if($isCurrent)<small class="py-run-now">This month</small>@endif
                            </div>
                            <div class="py-run-body">
                                <div class="py-run-title">
                                    <b>{{ $run->periodLabel() }}</b>
                                    <span class="b {{ $run->statusBadgeClass() }}">{{ $run->statusLabel() }}</span>
                                </div>
                                <div class="py-run-meta">
                                    <span>{{ $run->isDraft() ? 'Working days —' : $run->working_days.' working days' }}</span>
                                    <span class="py-sep">·</span>
                                    <span>{{ $employees }} {{ \Illuminate\Support\Str::plural('employee', $employees) }}</span>
                                    @unless($run->isDraft())
                                        <span class="py-sep">·</span>
                                        <span>Gross {{ $inr($gross) }}</span>
                                    @endunless
                                </div>
                            </div>
                            <div class="py-run-net">
                                <small>Net</small>
                                <b>{{ $run->isDraft() ? '—' : $inr($net) }}</b>
                            </div>
                            <span class="py-run-go">View</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if($canProcess)
        <div class="modal fade" id="payrollOpenModal" tabindex="-1" aria-labelledby="payrollOpenTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('payroll.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="payrollOpenTitle">Open a pay period</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-modal-body">
                        <p class="py-modal-help">If the month already exists, it opens that period instead of creating a duplicate.</p>
                        <div class="py-modal-grid">
                            <div>
                                <label class="form-label">Month *</label>
                                <select class="in w-100" name="period_month" required>
                                    @foreach($monthNames as $number => $name)
                                        <option value="{{ $number }}" @selected(old('period_month', $thisMonth) == $number)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Year *</label>
                                <input class="in w-100" type="number" name="period_year" min="2000" max="2100" value="{{ old('period_year', $thisYear) }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn pr" type="submit">Open period</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
