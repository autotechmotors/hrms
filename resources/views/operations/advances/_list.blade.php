@php
    $money = fn ($value) => '₹'.number_format((float) $value, 2);
@endphp
<div
    class="ad-body"
    data-page="{{ $advances->currentPage() }}"
    data-last="{{ $advances->lastPage() }}"
    data-total="{{ $advances->total() }}"
    data-active="{{ $stats['active'] }}"
    data-deducted="{{ $stats['deducted'] }}"
    data-balance="{{ number_format($stats['balance'], 2) }}"
>
    <div class="ad-cards">
        @forelse($advances as $advance)
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
                    <span class="b {{ $advance->status === 'active' ? 'wn' : ($advance->status === 'completed' ? 'ok' : '') }}">{{ $advance->status === 'active' ? 'Active' : ($advance->status === 'completed' ? 'Deducted' : 'Closed') }}</span>
                </div>
                <div class="ad-card-title">{{ $advance->title }}</div>
                <div class="ad-facts">
                    <div><span>Amount</span><b>{{ $money($advance->amount) }}</b></div>
                    <div><span>Salary month</span><b>{{ $advance->starts_on->format('M Y') }}</b></div>
                </div>
                @if($canEdit && $advance->status === 'active')
                    <div class="ad-ops">
                        <button class="btn sm ad-close" type="button" data-url="{{ route('advances.close', $advance) }}">Close</button>
                    </div>
                @endif
            </article>
        @empty
            <p class="ad-empty">No advances match these filters.</p>
        @endforelse
    </div>

    <div class="tw ad-table">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Title</th>
                    <th class="ad-money">Amount</th>
                    <th>Salary month</th>
                    <th>Status</th>
                    <th class="ad-act">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($advances as $advance)
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
                    <td class="ad-title">{{ $advance->title }}</td>
                    <td class="ad-money">{{ $money($advance->amount) }}</td>
                    <td>{{ $advance->starts_on->format('M Y') }}</td>
                    <td><span class="b {{ $advance->status === 'active' ? 'wn' : ($advance->status === 'completed' ? 'ok' : '') }}">{{ $advance->status === 'active' ? 'Active' : ($advance->status === 'completed' ? 'Deducted' : 'Closed') }}</span></td>
                    <td class="ad-act">
                        @if($canEdit && $advance->status === 'active')
                            <button class="btn sm ad-close" type="button" data-url="{{ route('advances.close', $advance) }}">Close</button>
                        @else
                            <span class="ad-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="ad-empty">No advances match these filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pgn">
        <span>{{ $advances->firstItem() ?? 0 }}–{{ $advances->lastItem() ?? 0 }} of {{ $advances->total() }}</span>
        <div>{{ $advances->links() }}</div>
    </div>
</div>
