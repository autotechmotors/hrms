<template id="leaveTypeChoiceSource">
    @foreach($typeChoices as $type)
        <option value="{{ $type->id }}" data-department="{{ $type->department_id }}">{{ $type->code }} — {{ $type->name }}{{ $type->is_paid ? '' : ' · '.__('Cutoff') }}</option>
    @endforeach
</template>
<div class="lv-stats g c4">
    <div class="card kp">
        <small><span class="lv-dot pending"></span>{{ __('Pending') }}</small>
        <b>{{ $stats['pending'] }}</b>
        <span>{{ __('Waiting for decision') }}</span>
    </div>
    <div class="card kp">
        <small><span class="lv-dot approved"></span>{{ __('Approved') }}</small>
        <b>{{ $stats['approved'] }}</b>
        <span>{{ __('Counted on the salary sheet') }}</span>
    </div>
    <div class="card kp">
        <small><span class="lv-dot rejected"></span>{{ __('Rejected') }}</small>
        <b>{{ $stats['rejected'] }}</b>
        <span>{{ __('Not counted') }}</span>
    </div>
    <div class="card kp">
        <small><span class="lv-dot types"></span>{{ __('Leave types') }}</small>
        <b>{{ $stats['types'] }}</b>
        <span>{{ __('One set for each department') }}</span>
    </div>
</div>

<section class="card lv-types">
    <header>
        <div>
            <h3>{{ __('Leave types') }}</h3>
            <p>{{ __('Each department has its own types. Cutoff takes that day’s pay. No cutoff pays the day.') }}</p>
        </div>
        @if($canEdit)
            <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#leaveTypeModal" data-mode="create">
                <x-i name="plus" :size="12" />{{ __('New') }}
            </button>
        @endif
    </header>
    <div class="cb">
        @if($types->isEmpty())
            <p class="lv-empty">{{ __('No leave types for this department yet.') }}</p>
        @else
            <div class="lv-chips">
                @foreach($types as $type)
                    <article class="lv-chip {{ $canEdit ? 'has-ops' : '' }}">
                        <div class="lv-chip-code">{{ $type->code }}</div>
                        <div class="lv-chip-body">
                            <div class="lv-chip-top">
                                <b title="{{ $type->name }}">{{ $type->name }}</b>
                                <span class="b {{ $type->is_paid ? 'ok' : 'er' }}">{{ $type->is_paid ? __('No cutoff') : __('Cutoff') }}</span>
                            </div>
                            <div class="lv-chip-meta">
                                <span>{{ $type->department?->name ?: __('All departments') }}</span>
                            </div>
                        </div>
                        @if($canEdit)
                            <div class="lv-chip-ops">
                                <button
                                    class="ib"
                                    type="button"
                                    title="{{ __('Edit') }}"
                                    aria-label="{{ __('Edit') }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#leaveTypeModal"
                                    data-mode="edit"
                                    data-action="{{ route('leave.types.update', $type) }}"
                                    data-department-id="{{ $type->department_id }}"
                                    data-name="{{ $type->name }}"
                                    data-code="{{ $type->code }}"
                                    data-cuts-pay="{{ $type->is_paid ? '0' : '1' }}"
                                ><x-i name="pen" :size="15" /></button>
                                <form method="post" action="{{ route('leave.types.destroy', $type) }}" data-confirm="{{ __('Delete this leave type? If leave already uses it, the type is closed and past leave stays.') }}">
                                    @csrf @method('delete')
                                    @include('operations.leave._retain', ['filters' => $filters])
                                    <button class="ib er" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-i name="trash" :size="15" /></button>
                                </form>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

<section class="card lv" id="leaveList">
    <form class="tbar lv-bar" method="get" action="{{ route('leave.index') }}" id="leaveFilters">
        <div class="sr lv-search">
            <span><x-i name="search" /></span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search employee…') }}" autocomplete="off">
        </div>
        <select class="in" name="department_id" data-filter>
            <option value="">{{ __('All departments') }}</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <select class="in" name="leave_type_id" data-filter>
            <option value="">{{ __('All types') }}</option>
            @foreach($typeChoices as $type)
                <option value="{{ $type->id }}" @selected((string) $filters['leave_type_id'] === (string) $type->id)>{{ $type->code }} — {{ $type->name }}@if($type->department) · {{ $type->department->name }}@endif</option>
            @endforeach
        </select>
        <select class="in" name="status" data-filter>
            <option value="">{{ __('All statuses') }}</option>
            @foreach(['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <a class="btn lv-reset" href="{{ route('leave.index') }}" title="{{ __('Reset') }}">
            <x-i name="reset" :size="14" />{{ __('Reset') }}
        </a>
        <div class="sp"></div>
        <span class="lv-meta">{{ $requests->total() }} {{ \Illuminate\Support\Str::plural('request', $requests->total()) }}</span>
    </form>

    <div data-live-list="leave" data-page="{{ $requests->currentPage() }}" data-last="{{ $requests->lastPage() }}">
        <div class="tw lv-table">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Dates') }}</th>
                        <th>{{ __('Days') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Status') }}</th>
                        @if($canEdit || $canApply)<th class="lv-act">{{ __('Action') }}</th>@endif
                    </tr>
                </thead>
                <tbody data-list-rows>
                @forelse($requests as $row)
                    @php
                        $badge = ['pending' => 'wn', 'approved' => 'ok', 'rejected' => 'er'][$row->status] ?? '';
                        $label = ['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')][$row->status] ?? ucfirst($row->status);
                        $ownsRow = (int) ($ownEmployee?->id ?? 0) === (int) $row->employee_id;
                        $isParent = $teamIds->contains($row->employee_id);
                        $canDecideRow = $row->status === 'pending' && ! ($ownsRow && ! auth()->user()->isSuperAdmin()) && ($canEdit || $isParent);
                        $canDeleteRow = $canEdit || ($ownsRow && $row->status === 'pending');
                    @endphp
                    <tr class="{{ $row->status === 'pending' ? 'lv-row-pending' : '' }}">
                        <td>
                            @if($row->employee)
                                <div class="who">
                                    <x-employee-avatar :employee="$row->employee" :size="32" />
                                    <div>
                                        <b>{{ $row->employee->full_name }}</b>
                                        <small>{{ $row->employee->employee_code }}@if($row->employee->department) · {{ $row->employee->department->name }}@endif</small>
                                    </div>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="lv-type">{{ $row->leaveType?->code ?? '—' }}</span>
                            <small class="lv-type-name">{{ $row->leaveType?->name }}</small>
                            <small class="lv-type-name">{{ $row->leaveType && ! $row->leaveType->is_paid ? __('Cutoff') : __('No cutoff') }}</small>
                        </td>
                        <td class="lv-dates">
                            <b>{{ $row->starts_on->format('d M') }} – {{ $row->ends_on->format('d M Y') }}</b>
                            @if($row->starts_on->equalTo($row->ends_on))
                                <small>{{ $row->starts_on->format('l') }}</small>
                            @endif
                        </td>
                        <td class="lv-days">{{ $row->days }}</td>
                        <td>
                            <span class="lv-reason" title="{{ $row->reason }}">{{ \Illuminate\Support\Str::limit($row->reason, 36) }}</span>
                            @if($row->status === 'rejected' && $row->decision_note)
                                <small class="lv-type-name">{{ __('Rejected') }}: {{ $row->decision_note }}</small>
                            @endif
                        </td>
                        <td><span class="b {{ $badge }}">{{ $label }}</span></td>
                        @if($canEdit || $canApply)
                            <td class="lv-act">@include('operations.leave._actions')</td>
                        @endif
                    </tr>
                @empty
                    <tr data-list-empty>
                        <td colspan="{{ ($canEdit || $canApply) ? 7 : 6 }}" class="lv-empty-row">{{ __('No leave requests match these filters.') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="lv-cards">
            @forelse($requests as $row)
                @php
                    $badge = ['pending' => 'wn', 'approved' => 'ok', 'rejected' => 'er'][$row->status] ?? '';
                    $label = ['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')][$row->status] ?? ucfirst($row->status);
                    $ownsRow = (int) ($ownEmployee?->id ?? 0) === (int) $row->employee_id;
                    $isParent = $teamIds->contains($row->employee_id);
                    $canDecideRow = $row->status === 'pending' && ! ($ownsRow && ! auth()->user()->isSuperAdmin()) && ($canEdit || $isParent);
                    $canDeleteRow = $canEdit || ($ownsRow && $row->status === 'pending');
                @endphp
                <article class="lv-card {{ $row->status === 'pending' ? 'is-pending' : '' }}">
                    <header>
                        @if($row->employee)
                            <div class="who">
                                <x-employee-avatar :employee="$row->employee" :size="32" />
                                <div>
                                    <b>{{ $row->employee->full_name }}</b>
                                    <small>{{ $row->employee->employee_code }}@if($row->employee->department) · {{ $row->employee->department->name }}@endif</small>
                                </div>
                            </div>
                        @endif
                        <span class="b {{ $badge }}">{{ $label }}</span>
                    </header>
                    <div class="lv-card-body">
                        <span class="lv-type">{{ $row->leaveType?->code ?? '—' }}</span>
                        <b>{{ $row->leaveType?->name }}</b>
                        <span>{{ $row->leaveType && ! $row->leaveType->is_paid ? __('Cutoff') : __('No cutoff') }}</span>
                        <span>{{ $row->starts_on->format('d M') }} – {{ $row->ends_on->format('d M Y') }} · {{ $row->days }} {{ __('Days') }}</span>
                        <span class="lv-reason">{{ $row->reason }}</span>
                        @if($row->status === 'rejected' && $row->decision_note)
                            <span>{{ __('Rejected') }}: {{ $row->decision_note }}</span>
                        @endif
                    </div>
                    @if($canEdit || $canApply)
                        <footer>@include('operations.leave._actions')</footer>
                    @endif
                </article>
            @empty
                <p class="lv-empty">{{ __('No leave requests match these filters.') }}</p>
            @endforelse
        </div>
        @include('partials.list-footer', ['paginator' => $requests])
    </div>
</section>
