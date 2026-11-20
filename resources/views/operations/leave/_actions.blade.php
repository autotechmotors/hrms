<div class="lv-ops">
    @if($canDecideRow)
        <form method="post" action="{{ route('leave.decide', $row) }}" class="lv-decide" data-confirm="{{ __('Approve leave for :name?', ['name' => $row->employee?->full_name]) }}">
            @csrf
            <input type="hidden" name="decision" value="approved">
            @include('operations.leave._retain', ['filters' => $filters])
            <button class="btn sm pr" type="submit">{{ __('Approve') }}</button>
        </form>
        <button
            class="btn sm"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#leaveRejectModal"
            data-action="{{ route('leave.decide', $row) }}"
            data-name="{{ $row->employee?->full_name }}"
        >{{ __('Reject') }}</button>
    @endif
    @if($canEdit)
        <button
            class="ib"
            type="button"
            title="{{ __('Edit') }}"
            aria-label="{{ __('Edit') }}"
            data-bs-toggle="modal"
            data-bs-target="#leaveRecordModal"
            data-mode="edit"
            data-action="{{ route('leave.update', $row) }}"
            data-employee-id="{{ $row->employee_id }}"
            data-leave-type-id="{{ $row->leave_type_id }}"
            data-starts-on="{{ $row->starts_on->toDateString() }}"
            data-ends-on="{{ $row->ends_on->toDateString() }}"
            data-reason="{{ $row->reason }}"
        >
            <x-i name="pen" :size="14" />
        </button>
    @endif
    @if($canDeleteRow)
        <form method="post" action="{{ route('leave.destroy', $row) }}" data-confirm="{{ __('Delete leave for :name? This cannot be undone.', ['name' => $row->employee?->full_name]) }}">
            @csrf @method('delete')
            @include('operations.leave._retain', ['filters' => $filters])
            <button class="ib er" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-i name="trash" :size="14" /></button>
        </form>
    @endif
</div>
