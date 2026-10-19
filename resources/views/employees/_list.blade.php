@php
    $canManage = $canManage ?? false;
    $canBlock = $canBlock ?? false;
@endphp
<div class="el-body" data-total="{{ $employees->total() }}" data-count="{{ $employees->count() }}">
    <div class="el-table tw">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>ID</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Checklist</th>
                    <th>Blocked</th>
                    <th class="el-act">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($employees as $employee)
                @php
                    $status = $employee->employmentStatus?->name ?? '—';
                    $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
                    $hasDocs = (int) ($employee->documents_count ?? 0) > 0;
                    $hasBank = (int) ($employee->active_bank_accounts_count ?? 0) > 0;
                    $pending = ! $hasDocs || ! $hasBank;
                    $blocked = (bool) $employee->is_blocked;
                @endphp
                <tr class="{{ $pending ? 'el-pending' : '' }} {{ $blocked ? 'el-blocked' : '' }}" data-employee-row="{{ $employee->id }}">
                    <td>
                        <a class="who el-link" href="{{ route('employees.show', $employee) }}">
                            <x-employee-avatar :employee="$employee" :size="32" />
                            <div>
                                <b>{{ $employee->full_name }}</b>
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
                            @if($canManage)
                                <a class="ib" href="{{ route('employees.edit', $employee) }}" title="Edit" aria-label="Edit"><x-i name="pen" :size="15" /></a>
                                <form class="el-del" method="post" action="{{ route('employees.destroy', $employee) }}" data-confirm="Delete {{ $employee->full_name }}? This cannot be undone.">
                                    @csrf @method('delete')
                                    <button class="ib er" type="submit" title="Delete" aria-label="Delete"><x-i name="trash" :size="15" /></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="el-empty">No employees match your filters</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="el-blocks">
        @forelse($employees as $employee)
            @php
                $status = $employee->employmentStatus?->name ?? '—';
                $badge = $employee->employmentStatus?->is_exit ? 'er' : (str_contains(strtolower($status), 'probation') ? 'wn' : 'ok');
                $hasDocs = (int) ($employee->documents_count ?? 0) > 0;
                $hasBank = (int) ($employee->active_bank_accounts_count ?? 0) > 0;
                $pending = ! $hasDocs || ! $hasBank;
                $blocked = (bool) $employee->is_blocked;
            @endphp
            <article class="el-card {{ $pending ? 'el-pending' : '' }} {{ $blocked ? 'el-blocked' : '' }}" data-employee-row="{{ $employee->id }}">
                <a class="el-card-main" href="{{ route('employees.show', $employee) }}">
                    <x-employee-avatar :employee="$employee" :size="40" />
                    <div class="el-card-copy">
                        <b>{{ $employee->full_name }}</b>
                        <small>{{ $employee->employee_code }} · {{ $employee->designation?->name ?? '—' }}</small>
                        <span>{{ $employee->department?->name ?? '—' }}</span>
                    </div>
                    <span class="b {{ $badge }}">{{ $status }}</span>
                </a>
                <div class="el-check">
                    <a class="el-pill {{ $hasDocs ? 'ok' : 'wn' }}" href="{{ route('employees.show', $employee) }}#documents">
                        <span class="el-dot"></span>Docs {{ $hasDocs ? 'done' : 'pending' }}
                    </a>
                    <a class="el-pill {{ $hasBank ? 'ok' : 'wn' }}" href="{{ route('employees.show', $employee) }}#bank">
                        <span class="el-dot"></span>Bank {{ $hasBank ? 'done' : 'pending' }}
                    </a>
                </div>
                <div class="el-card-block">
                    <span>Blocked</span>
                    @if($canBlock)
                        <label class="el-block-switch" title="{{ $blocked ? 'Blocked — click to unblock' : 'Active — click to block' }}">
                            <input type="checkbox" class="el-block-toggle" data-url="{{ route('employees.blocked', $employee) }}" @checked($blocked)>
                            <span class="el-switch-ui" aria-hidden="true"></span>
                            <span class="el-block-label">{{ $blocked ? 'Blocked' : 'Active' }}</span>
                        </label>
                    @else
                        <span class="b {{ $blocked ? 'er' : 'ok' }}">{{ $blocked ? 'Blocked' : 'Active' }}</span>
                    @endif
                </div>
                <div class="el-ops">
                    <a class="ib" href="{{ route('employees.show', $employee) }}" title="View" aria-label="View"><x-i name="eye" :size="15" /></a>
                    @if($canManage)
                        <a class="ib" href="{{ route('employees.edit', $employee) }}" title="Edit" aria-label="Edit"><x-i name="pen" :size="15" /></a>
                        <form class="el-del" method="post" action="{{ route('employees.destroy', $employee) }}" data-confirm="Delete {{ $employee->full_name }}? This cannot be undone.">
                            @csrf @method('delete')
                            <button class="ib er" type="submit" title="Delete" aria-label="Delete"><x-i name="trash" :size="15" /></button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="el-empty">No employees match your filters</div>
        @endforelse
    </div>

    <div class="pgn">
        <span>Showing {{ $employees->count() }} of {{ $employees->total() }} employees</span>
        <div class="el-pages">{{ $employees->links() }}</div>
    </div>
</div>
