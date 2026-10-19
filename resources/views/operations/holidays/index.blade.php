@extends('layouts.app')
@section('title', 'Holidays')
@section('content')
    @php
        $byDate = $holidays->keyBy(fn ($holiday) => $holiday->holiday_date->toDateString());
        $today = now()->toDateString();
        $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
        $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $counts = [
            'public' => $holidays->where('kind', 'public')->count(),
            'restricted' => $holidays->where('kind', 'restricted')->count(),
            'optional' => $holidays->where('kind', 'optional')->count(),
        ];
    @endphp

    <div class="ph">
        <div>
            <h1>Holidays</h1>
            <p>Click any date to add a holiday. Public days are non-working; restricted and optional follow leave settings.</p>
        </div>
        <div class="ac">
            <a class="btn" href="{{ route('holidays.index', ['year' => $year - 1]) }}"><x-i name="back" :size="14" />{{ $year - 1 }}</a>
            <span class="hc-year-label">{{ $year }}</span>
            <a class="btn" href="{{ route('holidays.index', ['year' => $year + 1]) }}">{{ $year + 1 }} →</a>
            @if($canEdit)
                <button class="btn pr" type="button" data-bs-toggle="modal" data-bs-target="#holidayModal" data-date="{{ now()->toDateString() }}">
                    <x-i name="plus" :size="14" />Add holiday
                </button>
            @endif
        </div>
    </div>

    @include('operations.errors')

    <div class="hc-stats g c3">
        <div class="card kp">
            <small><span class="hc-dot public"></span>Public</small>
            <b>{{ $counts['public'] }}</b>
            <span>Non-working days</span>
        </div>
        <div class="card kp">
            <small><span class="hc-dot restricted"></span>Restricted</small>
            <b>{{ $counts['restricted'] }}</b>
            <span>On calendar, leave-type aware</span>
        </div>
        <div class="card kp">
            <small><span class="hc-dot optional"></span>Optional</small>
            <b>{{ $counts['optional'] }}</b>
            <span>On calendar, leave-type aware</span>
        </div>
    </div>

    <div class="hc-layout">
        <section class="card hc-board">
            <header>
                <div>
                    <h3>{{ $year }} calendar</h3>
                    <p>@if($canEdit)Select a day to create a holiday.@else Holidays for your company.@endif</p>
                </div>
                <div class="hc-legend">
                    <span><i class="hc-dot public"></i>Public</span>
                    <span><i class="hc-dot restricted"></i>Restricted</span>
                    <span><i class="hc-dot optional"></i>Optional</span>
                    <span><i class="hc-dot today"></i>Today</span>
                </div>
            </header>
            <div class="cb hc-year">
                @foreach($monthNames as $month => $monthName)
                    @php
                        $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
                    @endphp
                    <div class="hc-month" data-month="{{ $month }}">
                        <div class="hc-month-head">{{ $monthName }}</div>
                        <div class="hc-weekdays">
                            @foreach($weekdays as $dayName)
                                <span>{{ $dayName }}</span>
                            @endforeach
                        </div>
                        <div class="hc-grid">
                            @php
                                $cursor = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                                $gridEnd = $start->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
                            @endphp
                            @while($cursor->lte($gridEnd))
                                @php
                                    $date = $cursor->toDateString();
                                    $isOutside = (int) $cursor->month !== (int) $month;
                                    $holiday = $byDate->get($date);
                                @endphp
                                @include('operations.holidays.partials.day', [
                                    'date' => $date,
                                    'holiday' => $holiday,
                                    'isOutside' => $isOutside,
                                    'today' => $today,
                                    'canEdit' => $canEdit,
                                ])
                                @php $cursor->addDay(); @endphp
                            @endwhile
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <aside class="hc-side">
            <section class="card ef-sec">
                <header>
                    <div>
                        <h3>This year</h3>
                        <p>{{ $holidays->count() }} holidays scheduled</p>
                    </div>
                </header>
                <div class="cb">
                    <div class="hc-list">
                        @forelse($holidays as $holiday)
                            <button
                                type="button"
                                class="hc-list-item kind-{{ $holiday->kind }}"
                                data-bs-toggle="modal"
                                data-bs-target="#holidayViewModal"
                                data-name="{{ $holiday->name }}"
                                data-kind="{{ $holiday->kind }}"
                                data-date="{{ $holiday->holiday_date->format('D, d M Y') }}"
                                data-iso="{{ $holiday->holiday_date->toDateString() }}"
                                @if($canEdit) data-delete="{{ route('holidays.destroy', $holiday) }}" @endif
                            >
                                <span class="hc-list-date">
                                    <b>{{ $holiday->holiday_date->format('d') }}</b>
                                    <small>{{ $holiday->holiday_date->format('M') }}</small>
                                </span>
                                <span class="hc-list-copy">
                                    <b>{{ $holiday->name }}</b>
                                    <small>{{ $holiday->holiday_date->format('l') }} · {{ ucfirst($holiday->kind) }}</small>
                                </span>
                            </button>
                        @empty
                            <p class="ed-empty">No holidays yet for {{ $year }}. Click a date on the calendar to add one.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </aside>
    </div>

    @if($canEdit)
        <div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true" data-bs-focus="false">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="{{ route('holidays.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add holiday</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <x-text-field name="holiday_date" label="Date" type="date" :value="old('holiday_date')" required />
                        <x-text-field name="name" label="Holiday name" :value="old('name')" required placeholder="e.g. Republic Day" />
                        <x-select-field name="kind" label="Kind" required>
                            <option value="public" @selected(old('kind', 'public') === 'public')>Public — non-working</option>
                            <option value="restricted" @selected(old('kind') === 'restricted')>Restricted</option>
                            <option value="optional" @selected(old('kind') === 'optional')>Optional</option>
                        </x-select-field>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn pr" type="submit">Save holiday</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="modal fade" id="holidayViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="holidayViewTitle">Holiday</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="hc-view">
                        <span class="b" id="holidayViewKind">public</span>
                        <b id="holidayViewName">—</b>
                        <small id="holidayViewDate">—</small>
                    </div>
                </div>
                <div class="modal-footer">
                    @if($canEdit)
                        <form id="holidayDeleteForm" method="post" action="#" data-confirm="Remove this holiday?">
                            @csrf @method('delete')
                            <button class="btn" type="submit" style="color:var(--er);border-color:#f0c9cf">Remove</button>
                        </form>
                    @endif
                    <button type="button" class="btn pr" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const isoPattern = /^\d{4}-\d{2}-\d{2}$/;

    function triggerEl(event) {
        const el = event.relatedTarget;
        return el?.closest?.('[data-date],[data-iso]') || el || null;
    }

    function isoFrom(el) {
        if (!el) return '';
        const iso = el.getAttribute('data-iso') || '';
        if (isoPattern.test(iso)) return iso;
        const date = el.getAttribute('data-date') || '';
        return isoPattern.test(date) ? date : '';
    }

    function markSelected(iso) {
        document.querySelectorAll('.hc-day.is-selected').forEach((day) => day.classList.remove('is-selected'));
        if (!iso) return;
        document.querySelectorAll('.hc-day[data-iso="' + iso + '"], .hc-day[data-date="' + iso + '"]').forEach((day) => {
            day.classList.add('is-selected');
        });
    }

    function setHolidayDate(input, date) {
        if (!input || !date) return;
        const picker = input._flatpickr;
        if (picker) {
            picker.setDate(date, true, 'Y-m-d');
            picker.jumpToDate(date);
            picker.redraw();
            return;
        }
        input.value = date;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const addModal = document.getElementById('holidayModal');
    if (addModal) {
        const applyFromModal = function (event) {
            const trigger = event ? triggerEl(event) : null;
            const date = isoFrom(trigger) || addModal.dataset.selectedDate || '';
            if (date) {
                addModal.dataset.selectedDate = date;
            }
            markSelected(addModal.dataset.selectedDate || '');
            setHolidayDate(addModal.querySelector('[name="holiday_date"]'), addModal.dataset.selectedDate || '');
        };

        addModal.addEventListener('show.bs.modal', applyFromModal);
        addModal.addEventListener('shown.bs.modal', function () {
            applyFromModal();
        });
        addModal.addEventListener('hidden.bs.modal', function () {
            markSelected('');
        });
    }

    const viewModal = document.getElementById('holidayViewModal');
    if (!viewModal) return;

    viewModal.addEventListener('show.bs.modal', function (event) {
        const trigger = triggerEl(event);
        if (!trigger) return;
        markSelected(isoFrom(trigger));
        document.getElementById('holidayViewTitle').textContent = trigger.getAttribute('data-name') || 'Holiday';
        document.getElementById('holidayViewName').textContent = trigger.getAttribute('data-name') || '—';
        document.getElementById('holidayViewDate').textContent = trigger.getAttribute('data-date') || '—';
        const kind = trigger.getAttribute('data-kind') || 'public';
        const kindEl = document.getElementById('holidayViewKind');
        kindEl.textContent = kind;
        kindEl.className = 'b ' + (kind === 'public' ? 'ok' : 'wn');

        const form = document.getElementById('holidayDeleteForm');
        if (form) {
            const action = trigger.getAttribute('data-delete');
            form.action = action || '#';
            form.hidden = !action;
        }
    });

    viewModal.addEventListener('hidden.bs.modal', function () {
        markSelected('');
    });
})();
</script>
@endpush
