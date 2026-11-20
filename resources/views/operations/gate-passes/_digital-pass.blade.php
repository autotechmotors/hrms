@php
    $employee = $gatePass->employee;
    $usable = $gatePass->isUsable();
    $isPending = $gatePass->isPending();
    $isRejected = $gatePass->isRejected();
    $isVoid = ! $usable && ! $isPending && ! $isRejected;
    $state = $usable ? 'approved' : ($isPending ? 'pending' : ($isRejected ? 'rejected' : 'expired'));
    $stateLabel = ['approved' => 'APPROVED', 'pending' => 'PENDING', 'rejected' => 'REJECTED', 'expired' => 'EXPIRED'][$state];
    $manager = $employee?->manager;
    $actorName = $isPending ? ($manager?->full_name ?? 'Admin') : ($gatePass->decider?->name ?? '—');
    $actorLabel = $isRejected ? 'REJECTED BY' : ($isPending ? 'WAITING FOR APPROVAL' : 'APPROVED BY');
    $actorTime = $isPending ? null : $gatePass->decided_at;
    $actorEmployee = $isPending ? $manager : $gatePass->decider?->employee;
    $photo = $employee?->photograph_path ? route('employees.photo', $employee) : null;
    $actorPhoto = $actorEmployee?->photograph_path ? route('employees.photo', $actorEmployee) : null;
    $actorInitials = strtoupper(collect(preg_split('/\s+/', trim($actorName)) ?: [])->map(fn ($part) => mb_substr($part, 0, 1))->filter()->take(2)->join('')) ?: 'A';
    $empInitials = strtoupper(collect(preg_split('/\s+/', trim((string) $employee?->full_name)) ?: [])->map(fn ($part) => mb_substr($part, 0, 1))->filter()->take(2)->join('')) ?: '?';
    $logo = asset('assets/images/logo-full.png');
    $companyName = $workspaceCompany->name ?? config('app.name');
    $canDecide = $canDecide ?? false;
@endphp

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<link href="{{ asset('assets/css/pass.css') }}?v=9" rel="stylesheet">
@endpush

<div class="pass-screen">
    <div class="pass-top">
        <span class="pass-logo">
            <img src="{{ $logo }}" alt="{{ $companyName }}">
        </span>
        <span class="pass-no">{{ $gatePass->number }}</span>
    </div>

    <h2 class="pass-heading">GATE PASS</h2>

    <div class="pass-card-wrap">
        <div class="pass-avatar {{ $isVoid ? 'is-void' : '' }}">
            @if($photo)
                <img src="{{ $photo }}" alt="{{ $employee?->full_name }}">
            @else
                <span class="pass-initials">{{ $empInitials }}</span>
            @endif
        </div>

        <div class="pass-card state-{{ $state }}">
            <h3 class="pass-name">{{ $employee?->full_name }}</h3>
            <span class="pass-id-pill">Employee ID · {{ $employee?->employee_code }}</span>
            @if($employee?->department)
                <div class="pass-dept">{{ $employee->department->name }}</div>
            @endif

            <div class="pass-duration">{{ $gatePass->duration_label }}</div>
            @if($gatePass->isEarlyLeave())
                <div class="pass-early-note">Not returning today · Attendance note</div>
            @endif

            <div class="pass-type type-{{ $gatePass->work_type }}">
                <span class="pass-label">Type</span>
                <strong>{{ $gatePass->work_type_label }}</strong>
            </div>

            <div class="pass-tiles">
                <div class="pass-tile">
                    <span class="pass-label">Date</span>
                    <strong>{{ $gatePass->formatted_date }}</strong>
                </div>
                <div class="pass-tile">
                    <span class="pass-label">Out Time</span>
                    <strong>{{ $gatePass->formatted_out_time }}</strong>
                </div>
            </div>

            <div class="pass-reason">
                <span class="pass-label">Reason</span>
                <p>{{ $gatePass->reason }}</p>
            </div>

            <div class="pass-actor">
                @if($actorPhoto)
                    <img src="{{ $actorPhoto }}" alt="">
                @else
                    <span class="pass-initials">{{ $actorInitials }}</span>
                @endif
                <div>
                    <span class="pass-label">{{ $actorLabel }}</span>
                    <strong>{{ $actorName }}</strong>
                    @if($actorTime)
                        <small>{{ $actorTime->format('d M Y, h:i A') }}</small>
                    @endif
                </div>
            </div>

            <div class="pass-status status-{{ $state }}">
                <span class="pass-status-main">
                    @if($state === 'approved')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    @elseif($state === 'pending')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    @endif
                    {{ $stateLabel }}
                </span>
                @if($state === 'approved' && $gatePass->expires_at)
                    <span class="pass-status-sub">Valid until {{ $gatePass->expires_at->format('h:i A') }}</span>
                @endif
            </div>

            <p class="pass-note">{{ $gatePass->machine_label }}</p>
            @if($gatePass->out_at)
                <p class="pass-note">Gate OUT {{ $gatePass->out_at->format('h:i A') }}@if($gatePass->in_at) · Return {{ $gatePass->in_at->format('h:i A') }}@endif</p>
            @endif
            @if($state === 'expired')
                <p class="pass-warning">{{ $gatePass->isOpenOuting() ? 'Expired while still out. Clock-in starts from the next punch. No return today is half-day early leave.' : 'This Gate Pass has expired and is no longer valid.' }}</p>
            @elseif($isRejected)
                <p class="pass-warning">{{ $gatePass->decision_note ? 'Reason: '.$gatePass->decision_note : 'This Gate Pass request was rejected. OUT machine was not opened.' }}</p>
            @elseif($isPending)
                <p class="pass-warning">Parent must approve before this person can punch OUT on the gate machine.</p>
            @elseif($usable)
                <p class="pass-note">{{ $gatePass->isOfficeWork() ? 'Office work hours stay in today’s working hours.' : 'Personal work time is cut from today’s working hours. Coming back early keeps the leftover minutes as work.' }}</p>
            @endif
        </div>
    </div>

    @if($canDecide || $usable)
        <div class="page-actions-bar {{ $canDecide ? 'pass-bar-decide' : 'pass-bar-usable' }}">
            @if($canDecide)
                <form method="post" action="{{ route('gate-passes.approve', $gatePass) }}" data-confirm="{{ __('Approve this Gate Pass?') }}">
                    @csrf
                    <button class="act-btn act-btn-success" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        Approve
                    </button>
                </form>
                <form method="post" action="{{ route('gate-passes.reject', $gatePass) }}" data-confirm="{{ __('Reject this Gate Pass? The employee is notified and the OUT machine stays closed.') }}">
                    @csrf
                    <input type="text" name="decision_note" class="pass-reject-note" required minlength="3" maxlength="500" placeholder="Reject reason" value="{{ old('decision_note') }}">
                    <button class="act-btn act-btn-light act-btn-danger-text" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        Reject
                    </button>
                </form>
            @else
                <button type="button" class="act-btn act-btn-primary" onclick="window.print()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>
                    Download PDF
                </button>
                <button type="button" class="act-btn act-btn-light act-btn-icon" onclick="window.print()" aria-label="Print" title="Print">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V4h10v5M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="6" rx="1"/></svg>
                </button>
            @endif
        </div>
    @endif
</div>
