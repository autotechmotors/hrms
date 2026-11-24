@php
    $actor = auth()->user();
    $canEdit = $actor->canEditGatePass($row);
    $canDelete = $actor->canDeleteGatePass($row);
    $canDecideRow = ($decide ?? true)
        && ($canDecide ?? false)
        && $row->isPending()
        && $row->employee
        && $actor->canDecideGatePassFor($row->employee);
    $live = ! empty($live);
    $bar = ($variant ?? 'row') === 'bar';
    $wrap = $wrap ?? ($bar ? 'pass-edit-ops' : 'lv-ops');
    $out = substr((string) $row->out_time, 0, 5);
    $duration = sprintf('%02d:%02d', intdiv((int) $row->duration_minutes, 60), ((int) $row->duration_minutes) % 60);
    $leave = $row->isEarlyLeave() ? 'early_leave' : 'return';
    $deleteConfirm = $row->isApproved()
        ? __('Delete :number and close OUT machine access? This cannot be undone.', ['number' => $row->number])
        : __('Delete :number? This cannot be undone.', ['number' => $row->number]);
@endphp
@if($canDecideRow || $canEdit || $canDelete)
    <div class="{{ $wrap }}">
        @if($canDecideRow)
            <form method="post" action="{{ route('gate-passes.approve', $row) }}" data-confirm="{{ __('Approve Gate Pass for :name?', ['name' => $row->employee?->full_name]) }}">
                @csrf
                <button class="btn sm pr" type="submit">{{ __('Approve') }}</button>
            </form>
            <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#gateRejectModal" data-action="{{ route('gate-passes.reject', $row) }}" data-name="{{ $row->employee?->full_name }}">{{ __('Reject') }}</button>
        @endif
        @if($canEdit)
            <button
                class="{{ $bar ? 'act-btn act-btn-light' : 'ib' }}"
                type="button"
                title="{{ __('Edit') }}"
                aria-label="{{ __('Edit') }}"
                data-gp-edit
                data-bs-toggle="modal"
                data-bs-target="#gateEditModal"
                data-action="{{ route('gate-passes.update', $row) }}"
                data-number="{{ $row->number }}"
                data-name="{{ $row->employee?->full_name }}"
                data-out="{{ $out }}"
                data-duration="{{ $duration }}"
                data-work="{{ $row->work_type }}"
                data-leave="{{ $leave }}"
                data-reason="{{ $row->reason }}"
            >
                @if($bar)
                    {{ __('Edit') }}
                @else
                    <x-i name="pen" :size="14" />
                @endif
            </button>
        @endif
        @if($canDelete)
            <form method="post" action="{{ route('gate-passes.destroy', $row) }}" class="gp-row-delete" data-confirm="{{ $deleteConfirm }}" @if($live) data-live="1" @endif>
                @csrf
                @method('delete')
                <button class="{{ $bar ? 'act-btn act-btn-light act-btn-danger-text' : 'ib er' }}" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                    @if($bar)
                        {{ __('Delete') }}
                    @else
                        <x-i name="trash" :size="14" />
                    @endif
                </button>
            </form>
        @endif
    </div>
@endif
