@extends('layouts.app')
@section('title', $employee->full_name)
@section('content')
    @php
        $prev = $month->copy()->subMonth()->startOfMonth()->toDateString();
        $next = $month->copy()->addMonth()->startOfMonth()->toDateString();
        $days = collect($payload['days']);
        $open = $days->firstWhere('date', $selected) ?? $days->first();
    @endphp

    <div class="ph">
        <div>
            <a class="ed-back" href="{{ route('attendance.index', ['date' => $selected]) }}"><x-i name="back" :size="14" />Attendance</a>
            <h1>{{ $employee->full_name }}</h1>
            <p>{{ $employee->employee_code }} · {{ $employee->department?->name ?? 'No department' }} · {{ $month->format('F Y') }}</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('attendance.show', ['employee' => $employee, 'date' => $prev]) }}" title="Previous month"><x-i name="back" :size="14" /></a>
            <span class="att-date-label">{{ $month->format('F Y') }}</span>
            <a class="btn" href="{{ route('attendance.show', ['employee' => $employee, 'date' => $next]) }}" title="Next month">→</a>
            <span class="att-fresh" id="attFresh">Updated {{ $payload['refreshed_at'] }}</span>
        </div>
    </div>

    <div class="ed" id="attRecord"
        data-feed="{{ route('attendance.employee.feed', $employee) }}"
        data-month="{{ $month->format('Y-m') }}"
        data-selected="{{ $selected }}"
        data-can-edit="{{ $canEdit ? '1' : '0' }}"
        data-update="{{ route('attendance.day.update', $employee) }}"
        data-delete="{{ route('attendance.day.destroy', $employee) }}"
        data-csrf="{{ csrf_token() }}"
        data-since="{{ $payload['since'] }}">
        <section class="card ed-hero">
            <div class="ed-hero-main">
                <x-employee-avatar :employee="$employee" :size="64" class="ed-av" />
                <div class="ed-hero-copy">
                    <h2>{{ $employee->full_name }}</h2>
                    <p>{{ $employee->employee_code }}@if($employee->designation) · {{ $employee->designation->name }}@endif</p>
                </div>
            </div>
            <div class="ed-hero-meta">
                <div>
                    <small>Days in {{ $month->format('F') }}</small>
                    <b id="attDayCount">{{ $days->count() }}</b>
                </div>
                <div>
                    <small>Entries</small>
                    <b id="attEntryCount">{{ $days->filter(fn ($day) => $day['in'])->count() }}</b>
                </div>
                <div>
                    <small>Exits</small>
                    <b id="attExitCount">{{ $days->filter(fn ($day) => $day['out'])->count() }}</b>
                </div>
                <div>
                    <small>Record</small>
                    <b>Month</b>
                </div>
            </div>
        </section>

        <div class="att-split">
            <section class="card ef-sec">
                <header>
                    <div>
                        <h3>Attendance record</h3>
                        <p>First face of the day is clock in. Another face within 2 minutes is the same visit. The next face is clock out.</p>
                    </div>
                </header>
                <div class="tw">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>Worked</th>
                                <th>Status</th>
                                @if($canEdit)<th>Action</th>@endif
                            </tr>
                        </thead>
                        <tbody id="attDays">
                            @forelse($days as $day)
                                <tr class="att-day {{ $day['date'] === $selected ? 'is-on' : '' }}" data-date="{{ $day['date'] }}" tabindex="0">
                                    <td>{{ $day['label'] }}</td>
                                    <td class="att-time js-in">{{ $day['in'] ?: '—' }}</td>
                                    <td class="att-time js-out">{{ $day['out'] ?: '—' }}</td>
                                    <td class="js-worked">{{ $day['worked'] }}</td>
                                    <td><span class="b {{ $day['status_class'] }} js-status">{{ $day['status'] }}</span></td>
                                    @if($canEdit)
                                        <td class="att-act">
                                            <button type="button" class="ib" title="Edit" data-edit-day data-date="{{ $day['date'] }}" data-label="{{ $day['label'] }}" data-in="{{ $day['in_input'] }}" data-out="{{ $day['out_input'] }}">
                                                <x-i name="pen" :size="14" />
                                            </button>
                                            <form method="post" action="{{ route('attendance.day.destroy', $employee) }}" data-confirm="Delete attendance for {{ $day['label'] }}?">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="work_date" value="{{ $day['date'] }}">
                                                <button class="ib" type="submit" title="Delete"><x-i name="trash" :size="14" /></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr class="att-empty-row">
                                    <td colspan="{{ $canEdit ? 6 : 5 }}" class="att-empty">No attendance in {{ $month->format('F Y') }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="att-detail-col">
                <section class="card ef-sec">
                    <header>
                        <div>
                            <h3 id="attDetailTitle">{{ $open['label'] ?? 'Selected day' }}</h3>
                            <p id="attDetailSub">One entry photo for this date.</p>
                        </div>
                    </header>
                    <div class="cb" id="attDetail">
                        @if($open)
                            <figure class="att-entry">
                                @if($open['in_photo'])
                                    <button type="button" class="att-zoom att-entry-frame" data-photo="{{ $open['in_photo'] }}">
                                        <img src="{{ $open['in_photo'] }}" alt="Entry photo for {{ $open['label'] }}">
                                    </button>
                                @else
                                    <p class="ed-empty">No entry photo for this date.</p>
                                @endif
                                <figcaption>
                                    <b>Entry</b>
                                    <span>{{ $open['in'] ?: '—' }}@if($open['in_note']) · {{ $open['in_note'] }}@endif</span>
                                </figcaption>
                            </figure>
                            <div class="att-times">
                                <div><small>In</small><b>{{ $open['in'] ?: '—' }}</b></div>
                                <div><small>Out</small><b>{{ $open['out'] ?: '—' }}</b></div>
                                <div><small>Worked</small><b>{{ $open['worked'] }}</b></div>
                            </div>
                            @if($open['punches'])
                                <ul class="att-log">
                                    @foreach($open['punches'] as $punch)
                                        <li><span>{{ $punch['time'] }}</span><span>{{ $punch['direction'] }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        @else
                            <p class="ed-empty">Punches appear here as soon as the device sends them.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>

    @if($canEdit)
        <div class="modal fade" id="attDayModal" tabindex="-1" aria-labelledby="attDayTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="{{ route('attendance.day.update', $employee) }}" id="attDayForm">
                    @csrf
                    <input type="hidden" name="work_date" id="attDayDate">
                    <div class="modal-header">
                        <h5 class="modal-title" id="attDayTitle">Edit attendance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body att-modal-body">
                        <p class="att-modal-date" id="attDayLabel"></p>
                        <div class="att-time-grid">
                            <div>
                                <label class="form-label" for="attDayIn">In</label>
                                <input class="in w-100" type="time" name="punch_in" id="attDayIn">
                            </div>
                            <div>
                                <label class="form-label" for="attDayOut">Out</label>
                                <input class="in w-100" type="time" name="punch_out" id="attDayOut">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="attView" class="att-view" hidden>
        <button type="button" class="att-view-x" data-close aria-label="Close">Close</button>
        <img id="attViewImg" alt="Attendance photo">
    </div>
@endsection

@push('scripts')
<script>
(function ($) {
    const root = document.getElementById('attRecord');
    if (!root) return;

    const state = {
        month: root.getAttribute('data-month'),
        selected: root.getAttribute('data-selected'),
        days: @json($payload['days']),
        since: root.getAttribute('data-since') || '',
        signature: ''
    };

    function text(value) {
        return value || '—';
    }

    function actions(day) {
        if (root.getAttribute('data-can-edit') !== '1') return '';
        return '<button type="button" class="ib" title="Edit" data-edit-day data-date="' + day.date + '" data-label="' + day.label + '" data-in="' + (day.in_input || '') + '" data-out="' + (day.out_input || '') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>'
            + '<form method="post" action="' + root.getAttribute('data-delete') + '" data-confirm="Delete attendance for ' + day.label + '?">'
            + '<input type="hidden" name="_token" value="' + root.getAttribute('data-csrf') + '">'
            + '<input type="hidden" name="_method" value="DELETE">'
            + '<input type="hidden" name="work_date" value="' + day.date + '">'
            + '<button class="ib" type="submit" title="Delete"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button></form>';
    }

    function entry(day) {
        const picture = day.in_photo
            ? '<button type="button" class="att-zoom att-entry-frame" data-photo="' + day.in_photo + '"><img src="' + day.in_photo + '" alt="Entry photo"></button>'
            : '<p class="ed-empty">No entry photo for this date.</p>';
        const note = day.in_note ? ' · ' + day.in_note : '';
        const rows = (day.punches || []).map(function (punch) {
            return '<li><span>' + punch.time + '</span><span>' + punch.direction + '</span></li>';
        }).join('');
        return '<figure class="att-entry">' + picture + '<figcaption><b>Entry</b><span>' + text(day.in) + note + '</span></figcaption></figure>'
            + '<div class="att-times"><div><small>In</small><b>' + text(day.in) + '</b></div><div><small>Out</small><b>' + text(day.out) + '</b></div><div><small>Worked</small><b>' + text(day.worked) + '</b></div></div>'
            + (rows ? '<ul class="att-log">' + rows + '</ul>' : '');
    }

    function findDay(date) {
        return state.days.find(function (day) { return day.date === date; }) || state.days[0] || null;
    }

    function paintDetail() {
        const day = findDay(state.selected);
        const box = document.getElementById('attDetail');
        const title = document.getElementById('attDetailTitle');
        const sub = document.getElementById('attDetailSub');
        if (!day) {
            title.textContent = 'Selected day';
            sub.textContent = 'No attendance in this month yet.';
            box.innerHTML = '<p class="ed-empty">Punches appear here as soon as the device sends them.</p>';
            return;
        }
        state.selected = day.date;
        title.textContent = day.label;
        sub.textContent = 'One entry photo for this date.';
        box.innerHTML = entry(day);
        document.querySelectorAll('#attDays tr[data-date]').forEach(function (row) {
            row.classList.toggle('is-on', row.getAttribute('data-date') === day.date);
        });
    }

    function cell(row, name, html) {
        const node = row.querySelector(name);
        if (node && node.innerHTML !== html) node.innerHTML = html;
    }

    function paintRows() {
        const body = document.getElementById('attDays');
        if (!state.days.length) return;
        const empty = body.querySelector('.att-empty-row');
        if (empty) empty.remove();
        state.days.forEach(function (day) {
            let row = body.querySelector('tr[data-date="' + day.date + '"]');
            if (!row) {
                row = document.createElement('tr');
                row.className = 'att-day';
                row.setAttribute('data-date', day.date);
                row.tabIndex = 0;
                row.innerHTML = '<td></td><td class="att-time js-in"></td><td class="att-time js-out"></td><td class="js-worked"></td><td><span class="b js-status"></span></td>'
                    + (root.getAttribute('data-can-edit') === '1' ? '<td class="att-act"></td>' : '');
                body.prepend(row);
            }
            const action = row.querySelector('.att-act');
            if (action) action.innerHTML = actions(day);
            row.children[0].textContent = day.label;
            cell(row, '.js-in', text(day.in));
            cell(row, '.js-out', text(day.out));
            const worked = row.querySelector('.js-worked');
            if (worked) worked.textContent = day.worked;
            const status = row.querySelector('.js-status');
            if (status) {
                status.textContent = day.status;
                status.className = 'b js-status ' + (day.status_class || '');
            }
        });
        document.getElementById('attDayCount').textContent = String(state.days.length);
        document.getElementById('attEntryCount').textContent = String(state.days.filter(function (day) { return day.in; }).length);
        document.getElementById('attExitCount').textContent = String(state.days.filter(function (day) { return day.out; }).length);
    }

    function apply(payload) {
        if (payload.since) state.since = payload.since;
        if (payload.unchanged) {
            document.getElementById('attFresh').textContent = 'Updated ' + payload.refreshed_at;
            return;
        }
        const next = JSON.stringify(payload.days || []);
        if (next === state.signature) {
            document.getElementById('attFresh').textContent = 'Updated ' + payload.refreshed_at;
            return;
        }
        state.signature = next;
        state.days = payload.days || [];
        paintRows();
        paintDetail();
        document.getElementById('attFresh').textContent = 'Updated ' + payload.refreshed_at;
    }

    function refresh() {
        $.getJSON(root.getAttribute('data-feed'), { month: state.month, date: state.selected, since: state.since })
            .done(apply);
    }

    $('#attDays').on('click', '[data-edit-day]', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const button = this;
        document.getElementById('attDayDate').value = button.getAttribute('data-date') || '';
        document.getElementById('attDayLabel').textContent = button.getAttribute('data-label') || '';
        document.getElementById('attDayIn').value = button.getAttribute('data-in') || '';
        document.getElementById('attDayOut').value = button.getAttribute('data-out') || '';
        const modal = document.getElementById('attDayModal');
        if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    $('#attDays').on('click', 'tr[data-date]', function (event) {
        if (event.target.closest('[data-photo], [data-edit-day], form, button')) return;
        state.selected = this.getAttribute('data-date');
        const url = new URL(window.location.href);
        url.searchParams.set('date', state.selected);
        history.replaceState(null, '', url);
        paintDetail();
    });
    $('#attDays').on('keydown', 'tr[data-date]', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            $(this).trigger('click');
        }
    });

    $(document).on('click', '[data-photo]', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const src = this.getAttribute('data-photo');
        if (!src) return;
        $('#attViewImg').attr('src', src);
        $('#attView').removeAttr('hidden');
    });
    $('#attView').on('click', function (event) {
        if (event.target === this || event.target.closest('[data-close]')) {
            this.hidden = true;
            $('#attViewImg').attr('src', '');
        }
    });
    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            document.getElementById('attView').hidden = true;
        }
    });

    apply({ days: state.days, refreshed_at: @json($payload['refreshed_at']), since: state.since });
    setInterval(refresh, 1500);
})(jQuery);
</script>
@endpush
