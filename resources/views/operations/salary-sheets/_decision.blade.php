@php
    $badge = match ($sheet->status) {
        'approved' => 'ok',
        'rejected' => 'er',
        default => 'wn',
    };
    $label = match ($sheet->status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
@endphp
<section class="card sg-decision" id="sgDecision">
    <div>
        <span class="b {{ $badge }}">{{ $label }}</span>
        @if($sheet->status === 'pending')
            <p>This salary is with the accountant. Approve accepts the pay. Reject needs a reason.</p>
        @elseif($sheet->status === 'approved')
            <p>Approved{{ $sheet->decider ? ' by '.$sheet->decider->name : '' }}{{ $sheet->decided_at ? ' on '.$sheet->decided_at->format('d M Y, H:i') : '' }}.</p>
        @else
            <p>Rejected{{ $sheet->decider ? ' by '.$sheet->decider->name : '' }}{{ $sheet->decided_at ? ' on '.$sheet->decided_at->format('d M Y, H:i') : '' }}.</p>
            @if($sheet->decision_note)
                <div class="sg-reason"><b>Reason.</b> {{ $sheet->decision_note }}</div>
            @endif
        @endif
    </div>
    @if($canDecide && $sheet->status === 'pending')
        <div class="sg-decide">
            <button class="btn pr sg-approve" type="button" data-url="{{ route('salary-sheets.approve', $sheet) }}">Approve</button>
            <button class="btn sg-reject" type="button" data-url="{{ route('salary-sheets.reject', $sheet) }}">Reject</button>
        </div>
    @endif
</section>
