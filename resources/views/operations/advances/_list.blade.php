@php
    $money = fn ($value) => '₹'.number_format((float) $value, 2);
    $statusMeta = [
        'pending' => ['label' => 'Pending', 'badge' => 'wn'],
        'active' => ['label' => 'Approved', 'badge' => 'ok'],
        'rejected' => ['label' => 'Rejected', 'badge' => 'er'],
        'completed' => ['label' => 'Deducted', 'badge' => 'ok'],
        'closed' => ['label' => 'Closed', 'badge' => ''],
    ];
@endphp
<div
    class="ad-body"
    data-live-list="advances"
    data-page="{{ $advances->currentPage() }}"
    data-last="{{ $advances->lastPage() }}"
    data-total="{{ $advances->total() }}"
    data-pending="{{ $stats['pending'] }}"
    data-active="{{ $stats['active'] }}"
    data-deducted="{{ $stats['deducted'] }}"
    data-balance="{{ number_format($stats['balance'], 2) }}"
>
    <div class="ad-cards" data-list-rows>
        @forelse($advances as $advance)
            @php $meta = $statusMeta[$advance->status] ?? ['label' => ucfirst($advance->status), 'badge' => '']; @endphp
            <article class="ad-card">
                <div class="ad-card-top">
                    @if($advance->employee)
                        <x-employee-avatar :employee="$advance->employee" :size="40" />
                        <div class="ad-copy">
                            <b>{{ $advance->employee->full_name }}</b>
                            <small>{{ $advance->employee->employee_code }}@if($advance->employee->department) · {{ $advance->employee->department->name }}@endif</small>
                        </div>
                    @else
                        <div class="ad-copy"><b>Removed employee</b></div>
                    @endif
                    <span class="b {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                </div>
                <div class="ad-card-title">{{ $advance->title }}</div>
                <div class="ad-facts">
                    <div><span>Amount</span><b>{{ $money($advance->amount) }}</b></div>
                    <div><span>Salary month</span><b>{{ $advance->starts_on->format('M Y') }}</b></div>
                </div>
                @if($advance->status === 'rejected' && $advance->decision_note)
                    <p class="ad-reason">Reason: {{ $advance->decision_note }}</p>
                @endif
                @if(($canDecide && $advance->status === 'pending') || ($canEdit && in_array($advance->status, ['pending', 'active', 'rejected', 'closed'], true)))
                    <div class="ad-ops">
                        @if($canDecide && $advance->status === 'pending')
                            <button class="btn sm pr ad-approve" type="button" data-url="{{ route('advances.approve', $advance) }}">Approve</button>
                            <button class="btn sm ad-reject" type="button" data-url="{{ route('advances.reject', $advance) }}">Reject</button>
                        @endif
                        @if($canEdit && $advance->status === 'pending')
                            <button
                                class="ib ad-edit"
                                type="button"
                                title="Edit"
                                data-url="{{ route('advances.update', $advance) }}"
                                data-title="{{ $advance->title }}"
                                data-amount="{{ number_format((float) $advance->amount, 2, '.', '') }}"
                            ><x-i name="pen" :size="14" /></button>
                            <button
                                class="ib ad-delete"
                                type="button"
                                title="Delete"
                                data-url="{{ route('advances.destroy', $advance) }}"
                            ><x-i name="trash" :size="14" /></button>
                        @elseif($canEdit && $advance->status === 'active')
                            <button class="btn sm ad-close" type="button" data-url="{{ route('advances.close', $advance) }}">{{ __('Close') }}</button>
                        @elseif($canEdit && in_array($advance->status, ['rejected', 'closed'], true))
                            <button
                                class="ib ad-delete"
                                type="button"
                                title="Delete"
                                data-url="{{ route('advances.destroy', $advance) }}"
                            ><x-i name="trash" :size="14" /></button>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <p class="ad-empty" data-list-empty>No advances match these filters.</p>
        @endforelse
    </div>

    <div class="tw ad-table">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th class="ad-money">Amount</th>
                    <th>{{ __('Salary month') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="ad-act">Action</th>
                </tr>
            </thead>
            <tbody data-list-rows>
            @forelse($advances as $advance)
                @php $meta = $statusMeta[$advance->status] ?? ['label' => ucfirst($advance->status), 'badge' => '']; @endphp
                <tr>
                    <td>
                        @if($advance->employee)
                            <div class="who">
                                <x-employee-avatar :employee="$advance->employee" :size="32" />
                                <div>
                                    <b>{{ $advance->employee->full_name }}</b>
                                    <small>{{ $advance->employee->employee_code }}@if($advance->employee->department) · {{ $advance->employee->department->name }}@endif</small>
                                </div>
                            </div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="ad-title">
                        {{ $advance->title }}
                        @if($advance->status === 'rejected' && $advance->decision_note)
                            <small class="ad-reason-inline">Reason: {{ $advance->decision_note }}</small>
                        @endif
                    </td>
                    <td class="ad-money">{{ $money($advance->amount) }}</td>
                    <td>{{ $advance->starts_on->format('M Y') }}</td>
                    <td><span class="b {{ $meta['badge'] }}">{{ $meta['label'] }}</span></td>
                    <td class="ad-act">
                        @if($canDecide && $advance->status === 'pending')
                            <button class="btn sm pr ad-approve" type="button" data-url="{{ route('advances.approve', $advance) }}">Approve</button>
                            <button class="btn sm ad-reject" type="button" data-url="{{ route('advances.reject', $advance) }}">Reject</button>
                        @endif
                        @if($canEdit && $advance->status === 'pending')
                            <button
                                class="ib ad-edit"
                                type="button"
                                title="Edit"
                                data-url="{{ route('advances.update', $advance) }}"
                                data-title="{{ $advance->title }}"
                                data-amount="{{ number_format((float) $advance->amount, 2, '.', '') }}"
                            ><x-i name="pen" :size="14" /></button>
                            <button
                                class="ib ad-delete"
                                type="button"
                                title="Delete"
                                data-url="{{ route('advances.destroy', $advance) }}"
                            ><x-i name="trash" :size="14" /></button>
                        @elseif($canEdit && $advance->status === 'active')
                            <button class="btn sm ad-close" type="button" data-url="{{ route('advances.close', $advance) }}">{{ __('Close') }}</button>
                        @elseif($canEdit && in_array($advance->status, ['rejected', 'closed'], true))
                            <button
                                class="ib ad-delete"
                                type="button"
                                title="Delete"
                                data-url="{{ route('advances.destroy', $advance) }}"
                            ><x-i name="trash" :size="14" /></button>
                        @elseif(! ($canDecide && $advance->status === 'pending'))
                            <span class="ad-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr data-list-empty>
                    <td colspan="6" class="ad-empty">No advances match these filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.list-footer', ['paginator' => $advances])
</div>
