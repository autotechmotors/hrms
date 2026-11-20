@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
    @php
        $prev = $date->copy()->subDay()->toDateString();
        $next = $date->copy()->addDay()->toDateString();
        $today = now()->toDateString();
        $isToday = $date->toDateString() === $today;
        $queryBase = array_filter([
            'q' => $filters['q'] ?: null,
            'department_id' => $filters['department_id'] ?: null,
            'status' => $filters['status'] ?: null,
        ]);
        $statusLabels = [
            'present' => 'Present',
            'late' => 'Late',
            'half_day' => 'Half day',
            'absent' => 'Absent',
            'on_leave' => 'On leave',
            'holiday' => 'Holiday',
            'weekly_off' => 'Weekly off',
            'unmarked' => 'Unmarked',
            'pending' => 'Not calculated',
        ];
        $statusClass = [
            'present' => 'ok',
            'late' => 'wn',
            'half_day' => 'wn',
            'absent' => 'er',
            'on_leave' => 'ac',
            'holiday' => 'ac',
            'weekly_off' => '',
            'unmarked' => 'wn',
            'pending' => '',
        ];
        $formatMinutes = function ($minutes) {
            if ($minutes === null || $minutes === '') {
                return '—';
            }
            $minutes = (int) $minutes;
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;

            return $h > 0 ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm', $m);
        };
        $formatTime = fn ($time) => \App\Support\Clock::time($time);
        $formatInput = fn ($time) => \App\Support\Clock::input($time);
    @endphp

    <div class="ph">
        <div>
            <h1>{{ __('Attendance') }}</h1>
            <p>A is a machine punch. M is a time saved by HR Admin or Super Admin. A manual day stays if the terminal was off.</p>
        </div>
        <div class="ac">
            <div class="att-nav">
                <a class="btn" href="{{ route('attendance.index', $queryBase + ['date' => $prev]) }}" title="Previous day">
                    <x-i name="back" :size="14" />
                </a>
                <span class="att-date-label">{{ $date->format('D, d M Y') }}</span>
                <a class="btn" href="{{ route('attendance.index', $queryBase + ['date' => $next]) }}" title="Next day">→</a>
                @unless($isToday)
                    <a class="btn" href="{{ route('attendance.index', $queryBase + ['date' => $today]) }}">Today</a>
                @endunless
            </div>
            @if($canEdit)
                <form method="post" action="{{ route('attendance.recalculate') }}" class="att-inline">
                    @csrf
                    <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">
                    <button class="btn" type="submit" title="Recalculate this day">
                        <x-i name="reset" :size="14" />Calculate
                    </button>
                </form>
                <button class="btn" type="button" data-bs-toggle="modal" data-bs-target="#attRuleModal">
                    <x-i name="set" :size="14" />Rules
                </button>
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#attPunchModal" data-mode="create">
                    <x-i name="plus" :size="14" />Add punch
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div class="att-stats g c4">
        <div class="card kp">
            <small><span class="att-dot present"></span>Present</small>
            <b id="attStatWorking">{{ $stats['working'] }}</b>
            <span id="attStatWorkingNote">{{ $stats['late'] }} late · {{ $stats['half_day'] }} half day</span>
        </div>
        <div class="card kp">
            <small><span class="att-dot absent"></span>Absent</small>
            <b id="attStatAbsent">{{ $stats['absent'] }}</b>
            <span>Marked absent for the day</span>
        </div>
        <div class="card kp">
            <small><span class="att-dot leave"></span>Leave / off</small>
            <b id="attStatOff">{{ $stats['on_leave'] + $stats['holiday'] + $stats['weekly_off'] }}</b>
            <span id="attStatOffNote">{{ $stats['on_leave'] }} leave · {{ $stats['holiday'] }} holiday · {{ $stats['weekly_off'] }} off</span>
        </div>
        <div class="card kp">
            <small><span class="att-dot pending"></span>Pending</small>
            <b id="attStatPending">{{ $stats['pending'] + $stats['unmarked'] }}</b>
            <span id="attStatPendingNote">{{ $stats['pending'] }} not calculated · {{ $stats['unmarked'] }} unmarked</span>
        </div>
    </div>

    <section class="card att" id="attendanceList" data-since="{{ $stamp }}">
        <form class="tbar att-bar" method="get" action="{{ route('attendance.index') }}" id="attendanceFilters">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <div class="att-find">
                <div class="sr att-search">
                    <span><x-i name="search" /></span>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name, code…" autocomplete="off">
                </div>
                <button class="btn att-filter-open" id="attFilterOpen" type="button" aria-controls="attFilterSheet" aria-expanded="false" aria-label="Filter">
                    <x-i name="set" :size="18" />
                </button>
            </div>
            <div class="att-sheet" id="attFilterSheet">
                <div class="att-sheet-bar">
                    <b>Filters</b>
                    <button class="ib" id="attFilterClose" type="button" aria-label="Close filters">×</button>
                </div>
                <select class="in" name="department_id" data-filter form="attendanceFilters">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                <select class="in" name="status" data-filter form="attendanceFilters">
                    <option value="">All statuses</option>
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="att-sheet-actions">
                    <a class="btn att-reset" href="{{ route('attendance.index', ['date' => $date->toDateString()]) }}" title="Reset filters">
                        <x-i name="reset" :size="14" />Reset
                    </a>
                    <button class="btn pr" id="attFilterApply" type="button">Apply</button>
                </div>
            </div>
            <div class="att-sheet-bg" id="attFilterBg"></div>
            <div class="sp"></div>
            <span class="att-meta" id="attMeta">{{ $employees->total() }} {{ \Illuminate\Support\Str::plural('person', $employees->total()) }}</span>
            <span class="att-fresh" id="attFresh"></span>
        </form>

        @include('operations.attendance._list')

    </section>

    @if($canEdit)
        <div class="modal fade" id="attPunchModal" tabindex="-1" aria-labelledby="attPunchTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('attendance.punch') }}" id="attPunchForm">
                    @csrf
                    <input type="hidden" name="work_date" value="{{ $date->toDateString() }}">
                    @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                    @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                    @if($filters['status'])<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
                    <div class="modal-header">
                        <h5 class="modal-title" id="attPunchTitle">Add punch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body att-modal-body">
                        <p class="att-modal-date">{{ $date->format('l, d M Y') }}</p>
                        <p class="form-text">This save is marked M. Use it when the terminal was off. Delete the day later if the machine record should be used instead.</p>
                        <input type="hidden" name="employee_id" id="attEmployeeHidden" value="">
                        <label class="form-label">Employee *</label>
                        <select class="in w-100" id="attEmployee" required>
                            <option value="">Select employee</option>
                            @foreach($picker as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->employee_code }} — {{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <div class="att-time-grid">
                            <div>
                                <label class="form-label">Punch in</label>
                                <input class="in w-100" type="time" name="punch_in" id="attPunchIn">
                            </div>
                            <div>
                                <label class="form-label">Punch out</label>
                                <input class="in w-100" type="time" name="punch_out" id="attPunchOut">
                            </div>
                        </div>
                        <label class="form-label">Remarks</label>
                        <input class="in w-100" name="remarks" id="attRemarks" maxlength="255" placeholder="Optional note">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn pr">Save punch</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="attRuleModal" tabindex="-1" aria-labelledby="attRuleTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('attendance.rule') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="attRuleTitle">Attendance rules</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body att-modal-body">
                        <p class="att-modal-help">Rules apply when you calculate the day. Grace covers late arrival; half/full day thresholds use worked minutes.</p>
                        <div class="att-rule-grid">
                            <div>
                                <label class="form-label">Grace minutes *</label>
                                <input class="in w-100" type="number" name="grace_minutes" min="0" max="240" value="{{ old('grace_minutes', $rule->grace_minutes) }}" required>
                            </div>
                            <div>
                                <label class="form-label">Half day below (min)</label>
                                <input class="in w-100" type="number" name="half_day_minutes" min="1" max="1440" value="{{ old('half_day_minutes', $rule->half_day_minutes) }}" placeholder="Optional">
                            </div>
                            <div>
                                <label class="form-label">Full day minutes</label>
                                <input class="in w-100" type="number" name="full_day_minutes" min="1" max="1440" value="{{ old('full_day_minutes', $rule->full_day_minutes) }}" placeholder="Optional">
                            </div>
                        </div>
                        <label class="att-check">
                            <input type="checkbox" name="mark_absent_without_punch" value="1" @checked(old('mark_absent_without_punch', $rule->mark_absent_without_punch))>
                            <span>Mark absent when there is no punch</span>
                        </label>
                        <label class="att-check">
                            <input type="checkbox" name="store_selfie" value="1" @checked(old('store_selfie', $rule->store_selfie))>
                            <span>Store the scan selfie on the attendance punch</span>
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn pr">Save rules</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="attView" class="att-view" hidden>
        <button type="button" class="att-view-x btn" data-close>{{ __('Close') }}</button>
        <img id="attViewImg" alt="Attendance photo">
    </div>
@endsection

@push('scripts')
<script>
(function ($) {
    $(document).on('click', '#attendanceList [data-photo], #attView', function (event) {
        const view = document.getElementById('attView');
        if (!view) return;
        if (this.id === 'attView') {
            if (event.target === view || event.target.closest('[data-close]')) {
                view.hidden = true;
                document.getElementById('attViewImg').src = '';
            }
            return;
        }
        event.preventDefault();
        const src = this.getAttribute('data-photo');
        if (!src) return;
        document.getElementById('attViewImg').src = src;
        view.hidden = false;
    });
    $(document).on('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const view = document.getElementById('attView');
        if (view) view.hidden = true;
    });

    const form = $('#attendanceFilters');
    const list = document.getElementById('attendanceList');
    if (list) {
        const feed = @json(route('attendance.feed'));
        let since = list.getAttribute('data-since') || '';
        function setClock(cell, time, photo) {
            if (!cell) return;
            let clock = cell.querySelector('.js-clock');
            if (!clock) {
                clock = document.createElement('span');
                clock.className = 'js-clock';
                cell.prepend(clock);
            }
            clock.textContent = time;
            if (photo && !cell.querySelector('img')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'att-zoom';
                button.setAttribute('data-photo', photo);
                const img = document.createElement('img');
                img.className = 'att-shot';
                img.alt = 'Punch photo';
                img.src = photo;
                button.appendChild(img);
                cell.appendChild(button);
            }
        }
        function paintFeed(data) {
            if (data.since) since = data.since;
            const fresh = document.getElementById('attFresh');
            if (fresh) fresh.textContent = 'Updated ' + data.refreshed_at;
            if (data.unchanged) return;
            Object.keys(data.rows || {}).forEach(function (id) {
                const item = data.rows[id];
                if (!item) return;
                list.querySelectorAll('[data-employee="' + id + '"]').forEach(function (row) {
                    const worked = row.querySelector('.js-worked');
                    const status = row.querySelector('.js-status');
                    setClock(row.querySelector('.js-in'), item.in, item.in_photo);
                    setClock(row.querySelector('.js-out'), item.out, item.out_photo);
                    const portrait = row.querySelector('.js-portrait');
                    if (portrait && item.photo && portrait.getAttribute('src') !== item.photo) {
                        portrait.src = item.photo;
                        const opener = portrait.closest('[data-photo]');
                        if (opener) opener.setAttribute('data-photo', item.photo);
                    }
                    if (worked) worked.textContent = item.worked;
                    if (status) {
                        status.textContent = item.status;
                        status.className = 'b js-status ' + (item.status_class || '');
                    }
                    const mark = row.querySelector('.js-mark');
                    if (mark) {
                        mark.textContent = item.mark || '';
                        mark.className = 'att-mark js-mark ' + (item.mark === 'M' ? 'is-m' : 'is-a');
                        mark.title = item.mark === 'M' ? 'Manual' : 'Machine';
                        mark.hidden = !item.mark;
                    }
                    const edit = row.querySelector('[data-punch-in]');
                    if (edit) {
                        edit.setAttribute('data-punch-in', item.in_input || '');
                        edit.setAttribute('data-punch-out', item.out_input || '');
                    }
                });
            });
        }
        setInterval(function () {
            const params = new URLSearchParams(window.location.search);
            $.getJSON(feed, {
                date: params.get('date') || @json($date->toDateString()),
                q: params.get('q') || '',
                department_id: params.get('department_id') || '',
                status: params.get('status') || '',
                since: since
            }).done(paintFeed);
        }, 1500);
    }
    if (!form.length) return;

    const phone = () => window.matchMedia('(max-width: 700px)').matches;
    const sheet = document.getElementById('attFilterSheet');
    const sheetBg = document.getElementById('attFilterBg');
    const filterOpen = document.getElementById('attFilterOpen');
    let timer = null;
    let req = null;
    let loadingMore = false;
    let snapshot = null;

    function field(name) {
        return form.get(0).elements.namedItem(name);
    }

    function query(extra) {
        const params = new URLSearchParams();
        form.serializeArray().forEach(function (item) {
            if (String(item.value || '').trim() !== '') params.set(item.name, item.value);
        });
        if (extra) {
            Object.keys(extra).forEach(function (key) { params.set(key, extra[key]); });
        }
        params.delete('partial');
        return params;
    }

    function paintStats(node) {
        const read = function (name) { return node.getAttribute(name) || '0'; };
        const working = document.getElementById('attStatWorking');
        const workingNote = document.getElementById('attStatWorkingNote');
        const absent = document.getElementById('attStatAbsent');
        const off = document.getElementById('attStatOff');
        const offNote = document.getElementById('attStatOffNote');
        const pending = document.getElementById('attStatPending');
        const pendingNote = document.getElementById('attStatPendingNote');
        const meta = document.getElementById('attMeta');
        if (working) working.textContent = read('data-working');
        if (workingNote) workingNote.textContent = read('data-late') + ' late · ' + read('data-half') + ' half day';
        if (absent) absent.textContent = read('data-absent');
        if (off) off.textContent = String(Number(read('data-leave')) + Number(read('data-holiday')) + Number(read('data-off')));
        if (offNote) offNote.textContent = read('data-leave') + ' leave · ' + read('data-holiday') + ' holiday · ' + read('data-off') + ' off';
        if (pending) pending.textContent = String(Number(read('data-pending')) + Number(read('data-unmarked')));
        if (pendingNote) pendingNote.textContent = read('data-pending') + ' not calculated · ' + read('data-unmarked') + ' unmarked';
        if (meta) {
            const total = Number(read('data-total'));
            meta.textContent = total + (total === 1 ? ' person' : ' people');
        }
        if (filterOpen) {
            const active = (field('department_id') && field('department_id').value) || (field('status') && field('status').value);
            filterOpen.classList.toggle('on', !!active);
        }
    }

    function load(extra, append) {
        if (!phone()) return;
        if (req) req.abort();
        const params = query(extra);
        const url = form.attr('action') + '?' + params.toString();
        const current = req = $.ajax({
            url: url,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { partial: 1 }
        }).done(function (html) {
            const incoming = document.createElement('div');
            incoming.innerHTML = html;
            const next = incoming.querySelector('#attendanceResults');
            const host = document.getElementById('attendanceResults');
            if (!next || !host) return;
            if (append) {
                const cards = host.querySelector('.att-cards');
                next.querySelectorAll('.att-card').forEach(function (card) {
                    if (cards) cards.appendChild(card);
                });
                const body = host.querySelector('tbody');
                next.querySelectorAll('tbody tr[data-employee]').forEach(function (row) {
                    if (body) body.appendChild(row);
                });
                host.dataset.page = next.dataset.page;
                host.dataset.last = next.dataset.last;
                const pager = host.querySelector('.pgn');
                const nextPager = next.querySelector('.pgn');
                if (pager && nextPager) pager.replaceWith(nextPager);
            } else {
                host.replaceWith(next);
                paintStats(next);
                if (window.history.replaceState) window.history.replaceState({}, '', url);
            }
        }).always(function () {
            if (req !== current) return;
            loadingMore = false;
            req = null;
        });
    }

    function placeSheet() {
        if (!sheet || !sheetBg) return;
        if (phone()) {
            document.body.appendChild(sheetBg);
            document.body.appendChild(sheet);
            return;
        }
        const spacer = form.get(0).querySelector('.sp');
        if (sheet.parentElement !== form.get(0)) {
            form.get(0).insertBefore(sheet, spacer);
            form.get(0).insertBefore(sheetBg, spacer);
        }
    }

    function closeSheet(restore) {
        if (restore && snapshot) {
            ['department_id', 'status'].forEach(function (name) {
                const input = field(name);
                if (!input) return;
                input.value = snapshot[name];
                $(input).trigger('change.select2');
            });
        }
        if (sheet) sheet.classList.remove('open');
        if (sheetBg) sheetBg.classList.remove('open');
        if (filterOpen) filterOpen.setAttribute('aria-expanded', 'false');
    }

    function openSheet() {
        snapshot = {
            department_id: field('department_id') ? field('department_id').value : '',
            status: field('status') ? field('status').value : ''
        };
        placeSheet();
        if (sheet) sheet.classList.add('open');
        if (sheetBg) sheetBg.classList.add('open');
        if (filterOpen) filterOpen.setAttribute('aria-expanded', 'true');
    }

    form.on('submit', function (event) {
        if (!phone()) return;
        event.preventDefault();
        closeSheet(false);
        load();
    });
    form.on('change', '[data-filter]', function () {
        if (phone()) return;
        form.trigger('submit');
    });
    form.on('input', 'input[name="q"]', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.trigger('submit'); }, 350);
    });
    if (filterOpen) filterOpen.addEventListener('click', openSheet);
    $('#attFilterClose, #attFilterBg').on('click', function () { closeSheet(true); });
    $('#attFilterApply').on('click', function () {
        closeSheet(false);
        load();
    });
    window.addEventListener('resize', function () {
        placeSheet();
    });
    placeSheet();
    const firstResults = document.getElementById('attendanceResults');
    if (firstResults) paintStats(firstResults);

    const punchModal = document.getElementById('attPunchModal');
    if (punchModal) {
        const employee = document.getElementById('attEmployee');
        const employeeHidden = document.getElementById('attEmployeeHidden');

        function syncEmployee() {
            employeeHidden.value = employee.value || '';
        }

        employee.addEventListener('change', syncEmployee);

        punchModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            const title = document.getElementById('attPunchTitle');
            const punchIn = document.getElementById('attPunchIn');
            const punchOut = document.getElementById('attPunchOut');
            const remarks = document.getElementById('attRemarks');
            const mode = btn && btn.getAttribute('data-mode');

            if (mode === 'edit' && btn) {
                title.textContent = 'Edit punch';
                employee.value = btn.getAttribute('data-employee-id') || '';
                punchIn.value = btn.getAttribute('data-punch-in') || '';
                punchOut.value = btn.getAttribute('data-punch-out') || '';
                remarks.value = btn.getAttribute('data-remarks') || '';
                employee.disabled = true;
            } else {
                title.textContent = 'Add punch';
                employee.disabled = false;
                employee.value = '';
                punchIn.value = '';
                punchOut.value = '';
                remarks.value = '';
            }
            syncEmployee();
        });

        $('#attPunchForm').on('submit', syncEmployee);
    }
})(jQuery);
</script>
@endpush
