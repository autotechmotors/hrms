@extends('layouts.app')
@section('title', 'Shifts & Rosters')
@section('content')
    @php
        $prevWeek = $start->copy()->subWeek()->toDateString();
        $nextWeek = $start->copy()->addWeek()->toDateString();
        $thisWeek = now()->startOfWeek()->toDateString();
        $queryBase = array_filter([
            'q' => $filters['q'] ?: null,
            'department_id' => $filters['department_id'] ?: null,
        ]);
        $fmt = fn ($time) => \App\Support\Clock::time($time);
        $today = now()->toDateString();
    @endphp

    <div class="ph">
        <div>
            <h1>{{ __('Shifts & Rosters') }}</h1>
            <p>Define shift templates, then assign them across the week.</p>
        </div>
        <div class="ac">
            <a class="btn sh-jump" data-week="{{ $prevWeek }}" href="{{ route('shifts.index', $queryBase + ['week' => $prevWeek]) }}" title="Previous week">
                <x-i name="back" :size="14" />
            </a>
            <span class="sh-week-label" id="shWeekLabel">{{ $start->format('d M') }} – {{ $end->format('d M Y') }}</span>
            <a class="btn sh-jump" data-week="{{ $nextWeek }}" href="{{ route('shifts.index', $queryBase + ['week' => $nextWeek]) }}" title="Next week">→</a>
            <a class="btn sh-jump" id="shThisWeek" data-week="{{ $thisWeek }}" href="{{ route('shifts.index', $queryBase + ['week' => $thisWeek]) }}" @if($isThisWeek) hidden @endif>This week</a>
            @if($canEdit)
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#shiftModal">
                    <x-i name="plus" :size="14" />Add shift
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div class="sh-stats g c4">
        <div class="card kp">
            <small><span class="sh-dot shifts"></span>Shift templates</small>
            <b id="shStatShifts">{{ $stats['shifts'] }}</b>
            <span>Reusable codes for the roster</span>
        </div>
        <div class="card kp">
            <small><span class="sh-dot assigned"></span>Assigned</small>
            <b id="shStatAssigned">{{ $stats['assigned'] }}</b>
            <span>Shift slots this week</span>
        </div>
        <div class="card kp">
            <small><span class="sh-dot off"></span>Weekly off</small>
            <b id="shStatOff">{{ $stats['off'] }}</b>
            <span>Marked as off day</span>
        </div>
        <div class="card kp">
            <small><span class="sh-dot open"></span>Open</small>
            <b id="shStatOpen">{{ $stats['open'] }}</b>
            <span id="shStatPeople">{{ $stats['people'] }} people × 7 days</span>
        </div>
    </div>

    <section class="card sh-templates">
        <header>
            <div>
                <h3>{{ __('Shift templates') }}</h3>
                <p>Codes used in the weekly roster below.</p>
            </div>
            @if($canEdit)
                <button class="btn sm" type="button" data-bs-toggle="modal" data-bs-target="#shiftModal">
                    <x-i name="plus" :size="12" />New
                </button>
            @endif
        </header>
        <div class="cb">
            @if($shifts->isEmpty())
                <p class="sh-empty">No shifts yet. Add a template to start assigning the roster.</p>
            @else
                <div class="sh-chips">
                    @foreach($shifts as $shift)
                        <article class="sh-chip">
                            <div class="sh-chip-code" title="{{ $shift->code }}">{{ $shift->code }}</div>
                            <div class="sh-chip-body">
                                <div class="sh-chip-top">
                                    <b title="{{ $shift->name }}">{{ $shift->name }}</b>
                                    @if($shift->is_flexible)
                                        <span class="b ac">Flexible</span>
                                    @endif
                                </div>
                                <div class="sh-chip-meta">
                                    <span>{{ $fmt($shift->starts_at) }} – {{ $fmt($shift->ends_at) }}</span>
                                    <span class="sh-chip-sep">·</span>
                                    <span>{{ (int) $shift->break_minutes }}m break</span>
                                </div>
                            </div>
                            @if($canEdit)
                                <form class="sh-chip-act" method="post" action="{{ route('shifts.destroy', $shift) }}" data-confirm="{{ __('Remove :name?', ['name' => $shift->name]) }}">
                                    @csrf @method('delete')
                                    <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                                    @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                                    @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                                    <button class="ib er" type="submit" title="Remove"><x-i name="trash" :size="14" /></button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="card sh" id="shiftRoster">
        <form class="tbar sh-bar" method="get" action="{{ route('shifts.index') }}" id="shiftFilters">
            <input type="hidden" name="week" value="{{ $start->toDateString() }}">
            <div class="sr sh-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search employee…" autocomplete="off">
            </div>
            <select class="in" name="department_id" data-filter>
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <a class="btn sh-reset" href="{{ route('shifts.index', ['week' => $start->toDateString()]) }}" title="Reset filters">
                <x-i name="reset" :size="14" />Reset
            </a>
            <div class="sp"></div>
            <span class="sh-meta" id="shCount">{{ $employees->total() }} {{ \Illuminate\Support\Str::plural('person', $employees->total()) }}</span>
        </form>

        @if($canEdit && $shifts->isNotEmpty())
            <form class="sh-bulk" method="post" action="{{ route('shifts.roster.bulk') }}" id="shiftBulkForm">
                @csrf
                <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                <div class="sh-bulk-copy">
                    <b>Fill week</b>
                    <span>Set the same shift for every day in one go.</span>
                </div>
                <select class="in sh-bulk-shift" name="shift_id" id="bulkShiftId" data-native="1">
                    <option value="">Select shift…</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}">{{ $shift->code }} — {{ $shift->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="mode" id="bulkMode" value="shift">
                <label class="sh-bulk-check" title="Skip Saturday and Sunday">
                    <input type="checkbox" name="weekdays_only" value="1">
                    <span>Weekdays only</span>
                </label>
                <button class="btn pr" type="submit" data-bulk-mode="shift">Apply to all</button>
                <button class="btn" type="submit" data-bulk-mode="off" title="Mark all days off">All off</button>
                <button class="btn" type="submit" data-bulk-mode="clear" title="Clear all assignments">Clear</button>
            </form>
        @endif

        @include('operations.shifts._board')
    </section>

    @if($canEdit)
        <div class="modal fade" id="shiftModal" tabindex="-1" aria-labelledby="shiftModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('shifts.store') }}">
                    @csrf
                    <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                    @if($filters['q'])<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                    @if($filters['department_id'])<input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">@endif
                    <div class="modal-header">
                        <h5 class="modal-title" id="shiftModalTitle">Add shift</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body sh-modal-body">
                        <div class="sh-modal-grid">
                            <div>
                                <label class="form-label">Name *</label>
                                <input class="in w-100" name="name" required value="{{ old('name') }}" placeholder="General">
                            </div>
                            <div>
                                <label class="form-label">Code *</label>
                                <input class="in w-100" name="code" required value="{{ old('code') }}" placeholder="GEN" maxlength="40">
                            </div>
                        </div>
                        <div class="sh-modal-grid">
                            <div>
                                <label class="form-label">Starts *</label>
                                <input class="in w-100" type="time" name="starts_at" required value="{{ old('starts_at', '09:00') }}">
                            </div>
                            <div>
                                <label class="form-label">Ends *</label>
                                <input class="in w-100" type="time" name="ends_at" required value="{{ old('ends_at', '18:00') }}">
                            </div>
                        </div>
                        <div class="sh-modal-grid">
                            <div>
                                <label class="form-label">Break minutes</label>
                                <input class="in w-100" type="number" name="break_minutes" min="0" max="600" value="{{ old('break_minutes', 60) }}">
                            </div>
                            <div class="sh-flex-wrap">
                                <label class="sh-check">
                                    <input type="checkbox" name="is_flexible" value="1" @checked(old('is_flexible'))>
                                    <span>Flexible timing</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn pr">Save shift</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function ($) {
    const form = $('#shiftFilters');
    const token = $('meta[name="csrf-token"]').attr('content');
    let timer = null;
    let req = null;

    function board() {
        return document.getElementById('shiftBoard');
    }

    function query(extra) {
        const params = new URLSearchParams();
        form.serializeArray().forEach(function (item) {
            if (String(item.value || '').trim() !== '') params.set(item.name, item.value);
        });
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                if (extra[key] === null || extra[key] === '') params.delete(key);
                else params.set(key, extra[key]);
            });
        }
        params.delete('partial');
        return params;
    }

    function paintStats(stats) {
        if (!stats) return;
        const assigned = document.getElementById('shStatAssigned');
        const off = document.getElementById('shStatOff');
        const open = document.getElementById('shStatOpen');
        const people = document.getElementById('shStatPeople');
        const count = document.getElementById('shCount');
        if (assigned) assigned.textContent = stats.assigned;
        if (off) off.textContent = stats.off;
        if (open) open.textContent = stats.open;
        if (people) people.textContent = stats.people + ' people × 7 days';
        if (count) count.textContent = stats.people + (Number(stats.people) === 1 ? ' person' : ' people');
    }

    function paintChrome(node) {
        const read = function (name) { return node.getAttribute(name) || ''; };
        paintStats({
            assigned: read('data-assigned'),
            off: read('data-off'),
            open: read('data-open'),
            people: read('data-people')
        });
        const shifts = document.getElementById('shStatShifts');
        if (shifts) shifts.textContent = read('data-shifts');
        const label = document.getElementById('shWeekLabel');
        if (label) label.textContent = read('data-label');
        document.querySelectorAll('input[name="week"]').forEach(function (input) {
            input.value = read('data-week');
        });
        document.querySelectorAll('.sh-jump').forEach(function (link) {
            let week = '';
            if (link.id === 'shThisWeek') week = read('data-this');
            else if (link.title === 'Previous week') week = read('data-prev');
            else if (link.title === 'Next week') week = read('data-next');
            if (!week) return;
            link.dataset.week = week;
            const href = new URL(link.getAttribute('href'), window.location.origin);
            href.searchParams.set('week', week);
            link.setAttribute('href', href.pathname + href.search);
        });
        const todayBtn = document.getElementById('shThisWeek');
        if (todayBtn) todayBtn.hidden = read('data-current') === '1';
    }

    function load(extra) {
        if (!form.length) return;
        if (req) req.abort();
        const params = query(extra);
        const url = form.attr('action') + '?' + params.toString();
        const host = board();
        if (host) host.classList.add('is-loading');
        const current = req = $.ajax({
            url: url,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { partial: 1 }
        }).done(function (html) {
            const incoming = document.createElement('div');
            incoming.innerHTML = html;
            const next = incoming.querySelector('#shiftBoard');
            const live = board();
            if (!next || !live) return;
            live.replaceWith(next);
            paintChrome(next);
            if (window.history.replaceState) window.history.replaceState({}, '', url);
        }).always(function () {
            if (req !== current) return;
            req = null;
            const live = board();
            if (live) live.classList.remove('is-loading');
        });
    }

    function postForm(formEl) {
        return $.ajax({
            url: formEl.action,
            method: 'POST',
            data: $(formEl).serialize(),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }
        }).done(function (payload) {
            if (window.toast) window.toast((payload && payload.message) || 'Saved.');
            paintStats(payload && payload.stats);
        }).fail(function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Could not update the roster.';
            if (window.toast) window.toast(message, 'er');
        });
    }

    form.on('submit', function (event) {
        event.preventDefault();
        load({ page: 1 });
    });
    form.on('change', '[data-filter]', function () {
        load({ page: 1 });
    });
    form.on('input', 'input[name="q"]', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { load({ page: 1 }); }, 280);
    });
    $(document).on('click', '.sh-reset', function (event) {
        event.preventDefault();
        form.find('[name="q"]').val('');
        form.find('[name="department_id"]').val('');
        load({ page: 1, q: null, department_id: null });
    });
    $(document).on('click', '.sh-jump', function (event) {
        event.preventDefault();
        load({ week: this.dataset.week, page: 1 });
    });
    $(document).on('click', '#shiftBoard .pgn a', function (event) {
        event.preventDefault();
        const url = new URL(this.href, window.location.origin);
        load({ page: url.searchParams.get('page') || 1 });
    });
    $(document).on('change', '.sh-pick', function () {
        const formEl = this.form;
        if (!formEl) return;
        const off = formEl.querySelector('[name="is_off"]');
        if (off) off.checked = false;
        const cell = this.closest('td');
        if (cell) cell.classList.add('is-saving');
        postForm(formEl).done(function (payload) {
            const markedOff = !!(payload.cell && payload.cell.off);
            const markedSet = !!(payload.cell && payload.cell.shift_id);
            if (cell) {
                cell.classList.toggle('is-off', markedOff);
                cell.classList.toggle('is-set', markedSet);
            }
            const label = formEl.querySelector('.sh-off');
            if (label) label.classList.toggle('on', markedOff);
            this.classList.toggle('is-muted', markedOff);
        }.bind(this)).fail(function () {
            load();
        }).always(function () {
            if (cell) cell.classList.remove('is-saving');
        });
    });
    $(document).on('change', '.sh-off input', function () {
        const formEl = this.form;
        if (!formEl) return;
        const select = formEl.querySelector('[name="shift_id"]');
        if (this.checked && select) select.value = '';
        const cell = this.closest('td');
        if (cell) cell.classList.add('is-saving');
        postForm(formEl).done(function (payload) {
            const markedOff = !!(payload.cell && payload.cell.off);
            if (cell) {
                cell.classList.toggle('is-off', markedOff);
                cell.classList.toggle('is-set', !markedOff && !!(payload.cell && payload.cell.shift_id));
            }
            const label = formEl.querySelector('.sh-off');
            if (label) label.classList.toggle('on', markedOff);
            if (select) select.classList.toggle('is-muted', markedOff);
        }).fail(function () {
            load();
        }).always(function () {
            if (cell) cell.classList.remove('is-saving');
        });
    });

    const bulk = $('#shiftBulkForm');
    if (bulk.length) {
        bulk.on('click', '[data-bulk-mode]', function () {
            $('#bulkMode').val($(this).data('bulk-mode'));
        });
        bulk.on('submit', function (event) {
            event.preventDefault();
            const formEl = this;
            const mode = $('#bulkMode').val();
            if (mode === 'shift' && ! $('#bulkShiftId').val()) {
                if (window.toast) window.toast(@json(__('Select a shift before applying to the week.')), 'wn');
                return;
            }
            const count = Number((board() && board().dataset.people) || 0);
            const days = bulk.find('[name="weekdays_only"]').is(':checked') ? @json(__('weekdays')) : @json(__('all 7 days'));
            const fill = function (phrase) { return phrase.replace(':count', count).replace(':days', days); };
            let message = fill(@json(__('Clear roster for :count people (:days)?')));
            if (mode === 'shift') message = fill(@json(__('Apply this shift to :count people for :days?')));
            if (mode === 'off') message = fill(@json(__('Mark :days as Off for :count people?')));
            const go = function () {
                postForm(formEl).done(function () { load(); });
            };
            if (window.askConfirm) {
                window.askConfirm(message, { label: mode === 'clear' ? @json(__('Clear')) : @json(__('Apply')), danger: mode === 'clear' }).then(function (ok) {
                    if (ok) go();
                });
                return;
            }
            go();
        });
    }

    $(document).on('change', '.sh-row-pick', function () {
        const select = this;
        const formEl = select.form;
        if (!formEl || !select.value) return;
        select.blur();
        const code = select.options[select.selectedIndex].text.replace(' all days', '');
        const go = function () {
            postForm(formEl).done(function () { load(); });
        };
        if (!window.askConfirm) {
            go();
            return;
        }
        window.askConfirm(@json(__('Apply :shift to this person’s whole week?')).replace(':shift', code)).then(function (ok) {
            if (!ok) {
                select.value = '';
                return;
            }
            go();
        });
    });
})(jQuery);
</script>
@endpush
