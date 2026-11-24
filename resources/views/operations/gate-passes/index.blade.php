@extends('layouts.app')
@section('title', 'Gate Pass')
@section('content')
    @php
        $statusLabels = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
        ];
        $hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['date'] !== '' || $filters['work_type'] !== '' || $filters['employee_id'] || $filters['today'];
    @endphp

    <div class="gp-page">
        <div class="ph">
            <div>
                <h1>{{ __('Gate Pass') }}</h1>
                <p>{{ $passes->total() }} {{ \Illuminate\Support\Str::plural('pass', $passes->total()) }} in your scope</p>
            </div>
            <div class="ac">
                @if($canManage)
                    <a class="btn" href="{{ route('gate-passes.settings') }}"><x-i name="set" :size="14" />Rules</a>
                @endif
                @if($canCreate)
                    <a class="btn pr" href="{{ route('gate-passes.create') }}"><x-i name="plus" :size="14" />New Gate Pass</a>
                @endif
            </div>
        </div>

        @include('operations.errors')

        <div class="lv-stats g c4">
            <a class="card kp" href="{{ route('gate-passes.index', ['status' => 'pending']) }}">
                <small>New requested</small>
                <b>{{ $stats['pending'] }}</b>
                <span>Waiting for approval</span>
            </a>
            <a class="card kp" href="{{ route('gate-passes.index', ['status' => 'approved', 'today' => 1]) }}">
                <small>Approved today</small>
                <b>{{ $stats['approved_today'] }}</b>
                <span>Decided today</span>
            </a>
            <a class="card kp" href="{{ route('gate-passes.index', ['status' => 'approved']) }}">
                <small>Active now</small>
                <b>{{ $stats['active'] }}</b>
                <span>Still valid</span>
            </a>
            <a class="card kp" href="{{ route('gate-passes.index') }}">
                <small>Total passes</small>
                <b>{{ $stats['total'] }}</b>
                <span>In your scope</span>
            </a>
        </div>

        @if($pendingToday->isNotEmpty())
            <section class="card gp-today">
                <header>
                    <div>
                        <h3>{{ __('Today\'s pending passes') }}</h3>
                        <p>Only people under you. Your own pass is not listed here.</p>
                    </div>
                </header>
                <div class="cb gp-today-list">
                    @foreach($pendingToday as $row)
                        <article class="gp-req">
                            <a class="gp-req-main" href="{{ route('gate-passes.show', $row) }}">
                                <x-employee-avatar :employee="$row->employee" :size="36" />
                                <div>
                                    <b>{{ $row->employee?->full_name }}</b>
                                    <small>{{ $row->employee?->employee_code }} · {{ $row->work_type_label }} · {{ $row->formatted_out_time }} · {{ $row->duration_label }}</small>
                                </div>
                                <span class="b wn">Pending</span>
                            </a>
                            @include('operations.gate-passes._actions', ['row' => $row, 'canDecide' => $canDecide, 'live' => true, 'wrap' => 'gp-req-ops'])
                        </article>
                    @endforeach
                </div>
            </section>
        @elseif(auth()->user()->employee?->isSupervisor())
            <section class="card gp-today gp-today-empty">
                <div class="cb" style="color:var(--mu)">No pending Gate Pass from people under you today.</div>
            </section>
        @endif

        <section class="card el lv" id="gatePassList">
            <form class="tbar el-bar lv-bar" method="get" action="{{ route('gate-passes.index') }}" id="gatePassFilters">
                <div class="el-find">
                    <div class="sr el-search lv-search">
                        <span><x-i name="search" /></span>
                        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search employee or GP number…" autocomplete="off">
                    </div>
                    <button class="btn el-filter-open {{ $hasFilters ? 'on' : '' }}" id="elFilterOpen" type="button" aria-controls="elFilterSheet" aria-expanded="false" aria-label="Filter">
                        <x-i name="set" :size="18" />
                    </button>
                </div>
                <div class="el-sheet" id="elFilterSheet">
                    <div class="el-sheet-bar">
                        <b>Filters</b>
                        <button class="ib" id="elFilterClose" type="button" aria-label="Close filters">×</button>
                    </div>
                    <select class="in" name="work_type" data-filter form="gatePassFilters">
                        <option value="">All types</option>
                        <option value="personal" @selected($filters['work_type'] === 'personal')>Personal Work</option>
                        <option value="office" @selected($filters['work_type'] === 'office')>Office Work</option>
                    </select>
                    <select class="in" name="status" data-filter form="gatePassFilters">
                        <option value="">All statuses</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="in" name="employee_id" data-filter form="gatePassFilters">
                        <option value="">All employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) $filters['employee_id'] === (string) $employee->id)>{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                    <input class="in" type="date" name="date" value="{{ $filters['date'] }}" data-filter form="gatePassFilters">
                    <label class="lv-check"><input type="checkbox" name="today" value="1" @checked($filters['today']) data-filter form="gatePassFilters"><span>Today</span></label>
                    <div class="el-sheet-actions">
                        <a class="btn el-reset lv-reset" href="{{ route('gate-passes.index') }}" title="Reset filters">
                            <x-i name="reset" :size="14" />Reset
                        </a>
                        <button class="btn pr" id="elFilterApply" type="submit" form="gatePassFilters">Apply</button>
                    </div>
                </div>
                <div class="el-sheet-bg" id="elFilterBg"></div>
                <div class="sp"></div>
                <span class="lv-meta">{{ $passes->total() }} {{ \Illuminate\Support\Str::plural('pass', $passes->total()) }}</span>
            </form>

            <div data-live-list="gate-passes" data-page="{{ $passes->currentPage() }}" data-last="{{ $passes->lastPage() }}">
            <div class="tw lv-table">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Pass') }}</th>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Out') }}</th>
                            <th>{{ __('Duration') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="lv-act">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody data-list-rows>
                    @forelse($passes as $row)
                        <tr class="cl {{ $row->isPending() ? 'lv-row-pending' : '' }}" onclick="if(!event.target.closest('form,button,a')) location.href='{{ route('gate-passes.show', $row) }}'">
                            <td>
                                <b>{{ $row->number }}</b>
                                <small>{{ $row->formatted_date }}</small>
                            </td>
                            <td>
                                @if($row->employee)
                                    <div class="who">
                                        <x-employee-avatar :employee="$row->employee" :size="32" />
                                        <div>
                                            <b>{{ $row->employee->full_name }}</b>
                                            <small>{{ $row->employee->employee_code }}@if($row->employee->department) · {{ $row->employee->department->name }}@endif</small>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $row->formatted_out_time }}</td>
                            <td>{{ $row->duration_label }}</td>
                            <td>{{ $row->work_type_label }}</td>
                            <td><span class="lv-reason" title="{{ $row->reason }}">{{ \Illuminate\Support\Str::limit($row->reason, 40) }}</span></td>
                            <td><span class="b {{ $row->status_badge }}">{{ $row->status_label }}</span></td>
                            <td class="lv-act" onclick="event.stopPropagation()">
                                @include('operations.gate-passes._actions', ['row' => $row, 'canDecide' => $canDecide, 'live' => true])
                            </td>
                        </tr>
                    @empty
                        <tr data-list-empty>
                            <td colspan="8" class="lv-empty-row">No Gate Passes match these filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="el-blocks gp-blocks" data-list-rows>
                @forelse($passes as $row)
                    <article class="el-card {{ $row->isPending() ? 'el-pending' : '' }}">
                        <a class="el-card-main" href="{{ route('gate-passes.show', $row) }}">
                            <x-employee-avatar :employee="$row->employee" :size="44" />
                            <div class="el-card-copy">
                                <div class="el-card-title">
                                    <b>{{ $row->employee?->full_name ?? '—' }}</b>
                                    <span class="b {{ $row->status_badge }}">{{ $row->status_label }}</span>
                                </div>
                                <small>{{ $row->number }} · {{ $row->work_type_label }}</small>
                                <span>{{ $row->formatted_date }} · {{ $row->formatted_out_time }} · {{ $row->duration_label }}</span>
                            </div>
                        </a>
                        <div class="el-card-body">
                            <div class="gp-card-reason">{{ $row->reason }}</div>
                        </div>
                        <footer class="el-card-foot">
                            <span class="el-pill {{ $row->isOfficeWork() ? 'wn' : 'ok' }}">{{ $row->work_type_label }}</span>
                            <div class="el-ops">
                                <a class="ib" href="{{ route('gate-passes.show', $row) }}" title="View" aria-label="View"><x-i name="eye" :size="15" /></a>
                                @include('operations.gate-passes._actions', ['row' => $row, 'canDecide' => $canDecide, 'live' => true])
                            </div>
                        </footer>
                    </article>
                @empty
                    <div class="el-empty" data-list-empty>No Gate Passes match these filters.</div>
                @endforelse
            </div>

            @include('partials.list-footer', ['paginator' => $passes])
            </div>
        </section>
    </div>

    @include('operations.gate-passes._edit-modal', ['live' => true])

    <div class="modal fade" id="gateRejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="post" id="gateRejectForm" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Reject') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body lv-modal-body">
                    <p class="lv-modal-help" id="gateRejectName"></p>
                    <label class="form-label">{{ __('Reason') }} *</label>
                    <textarea class="in w-100" name="decision_note" id="gateRejectNote" required minlength="3" maxlength="500" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn pr">{{ __('Reject') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function ($) {
    const rejectModal = document.getElementById('gateRejectModal');
    if (rejectModal) {
        rejectModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('gateRejectForm').action = btn ? btn.getAttribute('data-action') : '#';
            document.getElementById('gateRejectName').textContent = btn ? (btn.getAttribute('data-name') || '') : '';
            document.getElementById('gateRejectNote').value = '';
        });
    }

    const form = $('#gatePassFilters');
    if (!form.length) return;
    const phone = function () { return window.matchMedia('(max-width: 700px)').matches; };
    let timer = null;

    form.on('change', '[data-filter]', function () {
        if (phone()) return;
        form.trigger('submit');
    });
    form.on('input', 'input[name="q"]', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.trigger('submit'); }, 350);
    });

    const sheet = document.getElementById('elFilterSheet');
    const sheetBg = document.getElementById('elFilterBg');
    const sheetHome = form.get(0);
    const sheetAnchor = form.find('.sp').get(0);

    function placeSheet() {
        if (!sheet || !sheetBg) return;
        if (phone()) {
            document.body.appendChild(sheetBg);
            document.body.appendChild(sheet);
        } else if (sheetAnchor && sheet.nextElementSibling !== sheetAnchor) {
            sheetHome.insertBefore(sheet, sheetAnchor);
            sheetHome.insertBefore(sheetBg, sheetAnchor);
        }
    }

    function setSheet(open) {
        if (!sheet) return;
        placeSheet();
        sheet.classList.toggle('open', open);
        if (sheetBg) sheetBg.classList.toggle('open', open);
        document.body.classList.toggle('gp-sheet-open', open);
        $('#elFilterOpen').attr('aria-expanded', open ? 'true' : 'false');
    }

    placeSheet();
    $('#elFilterOpen').on('click', function () { setSheet(true); });
    $('#elFilterClose, #elFilterBg').on('click', function () { setSheet(false); });
    window.addEventListener('resize', placeSheet);
})(jQuery);
</script>
@endpush
