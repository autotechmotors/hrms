@php
    $canEdit = $canEdit ?? false;
    $canDelete = $canDelete ?? false;
    $canBlock = $canBlock ?? false;
    $canSalary = $canSalary ?? false;
    $credentialCounts = $credentialCounts ?? [];
@endphp
<div class="el-body" data-live-list="employees" data-total="{{ $employees->total() }}" data-count="{{ $employees->count() }}" data-page="{{ $employees->currentPage() }}" data-last="{{ $employees->lastPage() }}">
    <div class="el-table tw">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Department') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Checklist') }}</th>
                    <th>{{ __('Access Credentials') }}</th>
                    <th>{{ __('Blocked') }}</th>
                    <th class="el-act">Actions</th>
                </tr>
            </thead>
            <tbody data-list-rows>
            @forelse($employees as $employee)
                @php
                    $status = $employee->employmentStatus?->name ?? '—';
                    $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
                    $hasDocs = (int) ($employee->documents_count ?? 0) > 0;
                    $hasBank = (int) ($employee->active_bank_accounts_count ?? 0) > 0;
                    $pending = ! $hasDocs || ! $hasBank;
                    $blocked = (bool) $employee->is_blocked;
                    $today = now()->toDateString();
                    $spanKind = $employee->shiftSpans->first(function ($span) use ($today) {
                        return $span->starts_on->toDateString() <= $today
                            && ($span->ends_on === null || $span->ends_on->toDateString() >= $today);
                    })?->kind;
                    $night = ($spanKind ?? $employee->workHour?->shift_kind) === 'night';
                @endphp
                <tr class="{{ $pending ? 'el-pending' : '' }} {{ $blocked ? 'el-blocked' : '' }}" data-employee-row="{{ $employee->id }}">
                    <td>
                        <a class="who el-link" href="{{ route('employees.show', $employee) }}">
                            <x-employee-avatar :employee="$employee" :size="32" />
                            <div>
                                <span class="el-name"><b>{{ $employee->full_name }}</b><span class="et-badge {{ $night ? 'is-night' : 'is-day' }}">{{ $night ? 'Night' : 'Day' }}</span></span>
                                <small>{{ $employee->designation?->name ?? '—' }}</small>
                            </div>
                        </a>
                    </td>
                    <td><span class="el-id">{{ $employee->employee_code }}</span></td>
                    <td>{{ $employee->department?->name ?? '—' }}</td>
                    <td><span class="b {{ $badge }}">{{ $status }}</span></td>
                    <td>
                        <div class="el-check">
                            <a class="el-pill {{ $hasDocs ? 'ok' : 'wn' }}" href="{{ route('employees.show', $employee) }}#documents" title="{{ $hasDocs ? 'Documents added' : 'Documents pending' }}">
                                <span class="el-dot"></span>Docs {{ $hasDocs ? 'done' : 'pending' }}
                            </a>
                            <a class="el-pill {{ $hasBank ? 'ok' : 'wn' }}" href="{{ route('employees.show', $employee) }}#bank" title="{{ $hasBank ? 'Bank details added' : 'Bank details pending' }}">
                                <span class="el-dot"></span>Bank {{ $hasBank ? 'done' : 'pending' }}
                            </a>
                        </div>
                    </td>
                    <td>
                        @include('employees._credentials', ['employee' => $employee, 'counts' => $credentialCounts[$employee->id] ?? [], 'slots' => ($credentialSlots[$employee->id] ?? []), 'canAdd' => $canBlock])
                    </td>
                    <td>
                        @if($canBlock)
                            <label class="el-block-switch" title="{{ $blocked ? 'Blocked — click to unblock' : 'Active — click to block' }}">
                                <input type="checkbox" class="el-block-toggle" data-url="{{ route('employees.blocked', $employee) }}" @checked($blocked)>
                                <span class="el-switch-ui" aria-hidden="true"></span>
                                <span class="el-block-label">{{ $blocked ? 'Blocked' : 'Active' }}</span>
                            </label>
                        @else
                            <span class="b {{ $blocked ? 'er' : 'ok' }}">{{ $blocked ? 'Blocked' : 'Active' }}</span>
                        @endif
                    </td>
                    <td class="el-act">
                        <div class="el-ops">
                            <a class="ib" href="{{ route('employees.show', $employee) }}" title="View" aria-label="View"><x-i name="eye" :size="15" /></a>
                            @if($canEdit)
                                <a class="ib" href="{{ route('employees.edit', $employee) }}" title="Edit" aria-label="Edit"><x-i name="pen" :size="15" /></a>
                            @endif
                            @if($canSalary)
                                <a class="ib" href="{{ route('hours-pay.show', $employee) }}" title="Salary" aria-label="Salary"><x-i name="wallet" :size="15" /></a>
                            @endif
                            @if($canDelete)
                                <form class="el-del" method="post" action="{{ route('employees.destroy', $employee) }}" data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $employee->full_name]) }}">
                                    @csrf @method('delete')
                                    <button class="ib er" type="submit" title="Delete" aria-label="Delete"><x-i name="trash" :size="15" /></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr data-list-empty>
                    <td colspan="8" class="el-empty">No employees match your filters</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="el-blocks" data-list-rows>
        @forelse($employees as $employee)
            @php
                $status = $employee->employmentStatus?->name ?? '—';
                $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
                $hasDocs = (int) ($employee->documents_count ?? 0) > 0;
                $hasBank = (int) ($employee->active_bank_accounts_count ?? 0) > 0;
                $pending = ! $hasDocs || ! $hasBank;
                $blocked = (bool) $employee->is_blocked;
                $today = now()->toDateString();
                $spanKind = $employee->shiftSpans->first(function ($span) use ($today) {
                    return $span->starts_on->toDateString() <= $today
                        && ($span->ends_on === null || $span->ends_on->toDateString() >= $today);
                })?->kind;
                $night = ($spanKind ?? $employee->workHour?->shift_kind) === 'night';
            @endphp
            <article class="el-card {{ $pending ? 'el-pending' : '' }} {{ $blocked ? 'el-blocked' : '' }}" data-employee-row="{{ $employee->id }}">
                <a class="el-card-main" href="{{ route('employees.show', $employee) }}">
                    <x-employee-avatar :employee="$employee" :size="44" />
                    <div class="el-card-copy">
                        <div class="el-card-title">
                            <b>{{ $employee->full_name }}</b>
                            <span class="et-badge {{ $night ? 'is-night' : 'is-day' }}">{{ $night ? 'Night' : 'Day' }}</span>
                            <span class="b {{ $badge }}">{{ $status }}</span>
                        </div>
                        <small>{{ $employee->employee_code }} · {{ $employee->designation?->name ?? '—' }}</small>
                        <span>{{ $employee->department?->name ?? '—' }}</span>
                    </div>
                </a>
                <div class="el-card-body">
                    <div class="el-check">
                        <a class="el-pill {{ $hasDocs ? 'ok' : 'wn' }}" href="{{ route('employees.show', $employee) }}#documents">
                            <span class="el-dot"></span>Docs {{ $hasDocs ? 'done' : 'pending' }}
                        </a>
                        <a class="el-pill {{ $hasBank ? 'ok' : 'wn' }}" href="{{ route('employees.show', $employee) }}#bank">
                            <span class="el-dot"></span>Bank {{ $hasBank ? 'done' : 'pending' }}
                        </a>
                    </div>
                    <div class="el-card-access">
                        <span>Access</span>
                        @include('employees._credentials', ['employee' => $employee, 'counts' => $credentialCounts[$employee->id] ?? [], 'slots' => ($credentialSlots[$employee->id] ?? []), 'canAdd' => $canBlock])
                    </div>
                </div>
                <footer class="el-card-foot">
                    @if($canBlock)
                        <label class="el-block-switch" title="{{ $blocked ? 'Blocked — click to unblock' : 'Active — click to block' }}">
                            <input type="checkbox" class="el-block-toggle" data-url="{{ route('employees.blocked', $employee) }}" @checked($blocked)>
                            <span class="el-switch-ui" aria-hidden="true"></span>
                            <span class="el-block-label">{{ $blocked ? 'Blocked' : 'Active' }}</span>
                        </label>
                    @else
                        <span class="b {{ $blocked ? 'er' : 'ok' }}">{{ $blocked ? 'Blocked' : 'Active' }}</span>
                    @endif
                    <div class="el-ops">
                        <a class="ib" href="{{ route('employees.show', $employee) }}" title="View" aria-label="View"><x-i name="eye" :size="15" /></a>
                        @if($canEdit)
                            <a class="ib" href="{{ route('employees.edit', $employee) }}" title="Edit" aria-label="Edit"><x-i name="pen" :size="15" /></a>
                        @endif
                        @if($canSalary)
                            <a class="ib" href="{{ route('hours-pay.show', $employee) }}" title="Salary" aria-label="Salary"><x-i name="wallet" :size="15" /></a>
                        @endif
                        @if($canDelete)
                            <form class="el-del" method="post" action="{{ route('employees.destroy', $employee) }}" data-confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $employee->full_name]) }}">
                                @csrf @method('delete')
                                <button class="ib er" type="submit" title="Delete" aria-label="Delete"><x-i name="trash" :size="15" /></button>
                            </form>
                        @endif
                    </div>
                </footer>
            </article>
        @empty
            <div class="el-empty" data-list-empty>No employees match your filters</div>
        @endforelse
    </div>

    @include('partials.list-footer', ['paginator' => $employees])
</div>
