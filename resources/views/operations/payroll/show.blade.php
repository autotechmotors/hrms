@extends('layouts.app')
@section('title', $run->periodLabel().' payroll')
@section('content')
    @php
        $inr = fn ($n): string => '₹'.number_format((float) $n, 2);
        $label = $run->periodLabel();
        $empty = $run->items->isEmpty();
        $people = $totals['employees'];
        if ($run->isApproved()) {
            $nextClass = 'ok';
            $nextTitle = 'This month is locked';
            $nextHelp = 'Salaries are frozen and advance EMI was recovered. This screen does not send money. Use Bank Transfers if you need a file.';
            $nextAction = 'bank';
        } elseif ($run->isComputed() && $empty) {
            $nextClass = 'wn';
            $nextTitle = 'No one is on this list';
            $nextHelp = 'These employees have no salary assigned for '.$label.'. Assign a salary, then calculate again.';
            $nextAction = 'salary';
        } elseif ($run->isComputed()) {
            $nextClass = 'ac';
            $nextTitle = 'Review, then approve';
            $nextHelp = 'Check each person’s take-home. Approve locks this month and recovers advance EMI. It does not send money to the bank.';
            $nextAction = 'approve';
        } else {
            $nextClass = 'wn';
            $nextTitle = 'Calculate this month';
            $nextHelp = 'This fills the list from attendance, unpaid leave, and salary. Nothing is paid until you approve later.';
            $nextAction = 'calculate';
        }
    @endphp

    <a class="ed-back" href="{{ route('payroll.index') }}"><x-i name="back" :size="14" />All months</a>

    <div class="ph">
        <div>
            <h1>{{ $label }}</h1>
            <p>
                <span class="b {{ $run->statusBadgeClass() }}">{{ $run->statusLabel() }}</span>
                @if($run->isApproved())
                    · Locked · advance EMI recovered
                @elseif($run->isComputed())
                    · Ready to review · {{ $run->working_days }} working days
                @else
                    · Not calculated yet
                @endif
            </p>
        </div>
        <div class="ac">
            @if($canProcess && $run->isComputed() && ! $empty)
                <form method="post" action="{{ route('payroll.compute', $run) }}">
                    @csrf
                    <button class="btn" type="submit">Calculate again</button>
                </form>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <section class="py-next {{ $nextClass }}">
        <div class="py-next-icon" aria-hidden="true">
            @if($nextAction === 'bank')
                <x-i name="bank" :size="18" />
            @elseif($nextAction === 'approve')
                <x-i name="shield" :size="18" />
            @elseif($nextAction === 'salary')
                <x-i name="wallet" :size="18" />
            @else
                <x-i name="chart" :size="18" />
            @endif
        </div>
        <div class="py-next-body">
            <h3>{{ $nextTitle }}</h3>
            <p>{{ $nextHelp }}</p>
        </div>
        <div class="py-next-act">
            @if($nextAction === 'calculate' && $canProcess)
                <form method="post" action="{{ route('payroll.compute', $run) }}">
                    @csrf
                    <button class="btn pr" type="submit">Calculate this month</button>
                </form>
            @elseif($nextAction === 'approve' && $canApprove)
                <form method="post" action="{{ route('payroll.approve', $run) }}" data-confirm="Approve {{ $label }}? Advance EMI will be deducted from salary. Money is not sent to the bank.">
                    @csrf
                    <button class="btn pr" type="submit">Approve this month</button>
                </form>
            @elseif($nextAction === 'salary')
                <a class="btn pr" href="{{ route('salary.index') }}">Assign salary</a>
            @elseif($nextAction === 'bank')
                <a class="btn pr" href="{{ route('bank.index') }}">Open bank transfers</a>
            @endif
        </div>
    </section>

    <div class="py-stats g c4">
        <div class="card kp">
            <small><span class="py-dot periods"></span>People</small>
            <b>{{ $run->isDraft() ? '—' : $people }}</b>
            <span>{{ $run->isDraft() ? 'Appears after calculate' : ($people === 1 ? '1 employee' : $people.' employees') }}</span>
        </div>
        <div class="card kp">
            <small><span class="py-dot computed"></span>Earnings</small>
            <b>{{ $run->isDraft() || $empty ? '—' : $inr($totals['gross']) }}</b>
            <span>Gross before deductions</span>
        </div>
        <div class="card kp">
            <small><span class="py-dot draft"></span>Deductions</small>
            <b>{{ $run->isDraft() || $empty ? '—' : $inr($totals['deductions']) }}</b>
            <span>Including advance EMI</span>
        </div>
        <div class="card kp">
            <small><span class="py-dot approved"></span>Take-home</small>
            <b>{{ $run->isDraft() || $empty ? '—' : $inr($totals['net']) }}</b>
            <span>{{ $run->isDraft() ? 'Working days —' : $run->working_days.' working days' }}</span>
        </div>
    </div>

    <section class="card py-board">
        <header>
            <div>
                <h3>Who gets paid</h3>
                <p>Take-home is earnings minus deductions. Unpaid days come from absence or unpaid leave.</p>
            </div>
            @unless($empty)
                <span class="py-meta">{{ $people }} {{ \Illuminate\Support\Str::plural('person', $people) }}</span>
            @endunless
        </header>

        @if($empty)
            <div class="py-empty">
                <h3>{{ $run->isDraft() ? 'No salaries yet' : 'No one is on this list' }}</h3>
                <p>
                    @if($run->isDraft())
                        Calculate this month to build the list from attendance and salary.
                    @else
                        Assign a salary for {{ $label }}, then calculate again.
                    @endif
                </p>
                @if($run->isDraft() && $canProcess)
                    <form method="post" action="{{ route('payroll.compute', $run) }}">
                        @csrf
                        <button class="btn pr" type="submit">Calculate this month</button>
                    </form>
                @elseif($run->isComputed())
                    <a class="btn pr" href="{{ route('salary.index') }}">Assign salary</a>
                @endif
            </div>
        @else
            <div class="cb py-people">
                @foreach($run->items as $item)
                    @php
                        $earnings = $item->earningLines();
                        $deductions = $item->deductionLines();
                    @endphp
                    <article class="py-person">
                        <div class="py-person-top">
                            <div class="who">
                                @if($item->employee)
                                    <x-employee-avatar :employee="$item->employee" :size="40" />
                                    <div>
                                        <b>{{ $item->employee->full_name }}</b>
                                        <small>{{ $item->employee->employee_code }}@if($item->employee->department) · {{ $item->employee->department->name }}@endif</small>
                                    </div>
                                @else
                                    <div><b>Unknown employee</b></div>
                                @endif
                            </div>
                            <div class="py-person-net">
                                <small>Take-home</small>
                                <b>{{ $inr($item->net) }}</b>
                            </div>
                        </div>
                        <p class="py-days">{{ $item->daysSummary((int) $run->working_days) }}</p>
                        <div class="py-slip">
                            <div class="py-slip-col">
                                <h4>Earnings</h4>
                                @forelse($earnings as $line)
                                    <div class="py-kv"><small>{{ $line['name'] }}</small><span class="up">{{ $inr($line['amount']) }}</span></div>
                                @empty
                                    <div class="py-kv"><small>Earnings</small><span class="up">{{ $inr($item->gross) }}</span></div>
                                @endforelse
                                <div class="py-kv tot"><span>Total</span><span>{{ $inr($item->gross) }}</span></div>
                            </div>
                            <div class="py-slip-col">
                                <h4>Deductions</h4>
                                @forelse($deductions as $line)
                                    <div class="py-kv"><small>{{ $line['name'] }}</small><span class="dn">{{ $inr($line['amount']) }}</span></div>
                                @empty
                                    <div class="py-kv"><small>None</small><span>{{ $inr(0) }}</span></div>
                                @endforelse
                                <div class="py-kv tot"><span>Total</span><span>{{ $inr($item->deductions) }}</span></div>
                            </div>
                            <div class="py-slip-home">
                                <small>Take-home</small>
                                <b>{{ $inr($item->net) }}</b>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="py-sum">
                <span>Earnings <b>{{ $inr($totals['gross']) }}</b></span>
                <span>Deductions <b>{{ $inr($totals['deductions']) }}</b></span>
                <span class="py-sum-net">Take-home <b>{{ $inr($totals['net']) }}</b></span>
            </div>
        @endif
    </section>

    @if(! $run->isDraft() && $missing->isNotEmpty())
        <section class="card py-skip">
            <header>
                <div>
                    <h3>Not in this payroll · {{ $missing->count() }}</h3>
                    <p>No salary is assigned for {{ $label }}, so they were left out.</p>
                </div>
                <a class="btn sm" href="{{ route('salary.index') }}">Assign salary</a>
            </header>
            <div class="cb py-skip-list">
                @foreach($missing as $employee)
                    <div class="who py-skip-who">
                        <x-employee-avatar :employee="$employee" :size="28" />
                        <div>
                            <b>{{ $employee->full_name }}</b>
                            <small>{{ $employee->employee_code }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
