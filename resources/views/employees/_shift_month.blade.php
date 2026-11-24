@php
    $year = (int) ($year ?? now()->year);
    $month = (int) ($month ?? now()->month);
    $schedule = app(\App\Services\Operations\ShiftSchedule::class);
    $clocks = app(\App\Services\Operations\WorkHourResolver::class)->editor($employee);
    $shift = $schedule->present($employee, $year, $month);
    $canAssignShift = $schedule->canAssign(auth()->user(), $employee);
    $rows = $shift['ranges'];
    if ($rows === []) {
        $rows = [
            ['kind' => 'day', 'starts_on' => '', 'ends_on' => ''],
            ['kind' => 'night', 'starts_on' => '', 'ends_on' => ''],
        ];
    }
    $dayOn = $shift['continuous'] !== 'night';
    $nightOn = $shift['continuous'] !== 'day';
    $dayAll = $shift['continuous'] === 'day';
    $nightAll = $shift['continuous'] === 'night';
@endphp
<section class="card ef-sec" id="empShift"
    data-days="{{ $shift['days'] }}"
    data-start="{{ $shift['start'] }}"
    data-end="{{ $shift['end'] }}"
    data-load="{{ route('employees.shifts.show', $employee) }}"
    data-locked="{{ $canAssignShift ? '0' : '1' }}">
    <header>
        <div>
            <h3>{{ __('Day and night') }}</h3>
            <p>Which dates in {{ $shift['label'] }} use the day clock, and which use the night clock. Saving this month leaves other months as they are.</p>
        </div>
    </header>
    <div class="cb">
        <div class="ss-bar">
            <button class="ib" type="button" data-shift-nav data-year="{{ $shift['prev']['year'] }}" data-month="{{ $shift['prev']['month'] }}" title="Previous month" aria-label="Previous month"><x-i name="back" :size="16" /></button>
            <div>
                <b>{{ $shift['label'] }}</b>
                <small>{{ $shift['days'] }} days</small>
            </div>
            <button class="ib" type="button" data-shift-nav data-year="{{ $shift['next']['year'] }}" data-month="{{ $shift['next']['month'] }}" title="Next month" aria-label="Next month"><x-i name="right" :size="16" /></button>
        </div>
        <div class="ss-key" aria-hidden="true"><span class="is-day">Day</span><span class="is-night">Night</span></div>
        <div class="ss-cells" data-shift-cells>
            @foreach($shift['cells'] as $cell)
                <span class="ss-cell is-{{ $cell['kind'] }}{{ $cell['saved'] ? '' : ' is-soft' }}" data-cell="{{ $cell['date'] }}">{{ $cell['day'] }}</span>
            @endforeach
        </div>
        <p class="ss-note" data-shift-note>
            @if($shift['continuous'])
                All days use {{ $shift['continuous'] === 'night' ? 'Night' : 'Day' }}.
                @unless($shift['saved'])
                    Save to keep it.
                @endunless
            @else
                Every date in {{ $shift['label'] }} needs Day or Night.
            @endif
        </p>
        @if($canAssignShift)
            <form id="empShiftForm" method="post" action="{{ route('employees.shifts.update', $employee) }}">
                @csrf
                <input type="hidden" name="year" value="{{ $shift['year'] }}">
                <input type="hidden" name="month" value="{{ $shift['month'] }}">
                <input type="hidden" name="continuous" value="{{ $shift['continuous'] ?? '' }}" data-continuous-value>
                <div class="ss-grid">
                    <article class="ss-card{{ $dayOn ? '' : ' is-off' }}" data-shift-card="day">
                        <div class="ss-top">
                            <div>
                                <b>Day</b>
                                <small>Mon–Sat {{ $clocks['day_start'] }}–{{ $clocks['day_end'] }} · Sunday {{ $clocks['day_sunday_start'] }}–{{ $clocks['day_sunday_end'] }}</small>
                            </div>
                            <label class="et-switch">
                                <input type="checkbox" data-kind-on="day" @checked($dayOn)>
                                <span class="rl-ui" aria-hidden="true"></span>
                                <span data-kind-label="day">{{ $dayOn ? 'On' : 'Off' }}</span>
                            </label>
                        </div>
                        <label class="ss-all">
                            <input type="checkbox" data-all="day" @checked($dayAll)>
                            <span>All days</span>
                        </label>
                    </article>
                    <article class="ss-card{{ $nightOn ? '' : ' is-off' }}" data-shift-card="night">
                        <div class="ss-top">
                            <div>
                                <b>Night</b>
                                <small>Mon–Sat {{ $clocks['night_start'] }}–{{ $clocks['night_end'] }} · Sunday {{ $clocks['night_sunday_start'] }}–{{ $clocks['night_sunday_end'] }}</small>
                            </div>
                            <label class="et-switch">
                                <input type="checkbox" data-kind-on="night" @checked($nightOn)>
                                <span class="rl-ui" aria-hidden="true"></span>
                                <span data-kind-label="night">{{ $nightOn ? 'On' : 'Off' }}</span>
                            </label>
                        </div>
                        <label class="ss-all">
                            <input type="checkbox" data-all="night" @checked($nightAll)>
                            <span>All days</span>
                        </label>
                    </article>
                </div>
                <div class="ss-ranges" data-ranges @if($dayAll || $nightAll) hidden @endif>
                    @foreach($rows as $index => $row)
                        <div class="ss-range" data-range>
                            <label class="ss-field">
                                <span>Shift</span>
                                <select class="form-select" name="ranges[{{ $index }}][kind]" data-range-kind data-native aria-label="Shift">
                                    <option value="day" @selected($row['kind'] === 'day')>Day</option>
                                    <option value="night" @selected($row['kind'] === 'night')>Night</option>
                                </select>
                            </label>
                            <label class="ss-field ss-field-range">
                                <span>Dates</span>
                                <input type="hidden" name="ranges[{{ $index }}][starts_on]" data-from value="{{ $row['starts_on'] }}">
                                <input type="hidden" name="ranges[{{ $index }}][ends_on]" data-to value="{{ $row['ends_on'] }}">
                                <input class="form-control" type="text" data-range-pick readonly placeholder="Select dates" aria-label="Dates">
                            </label>
                            <button class="ib" type="button" data-range-remove title="Remove" aria-label="Remove"><x-i name="close" :size="14" /></button>
                        </div>
                    @endforeach
                </div>
                <template data-range-template>
                    <div class="ss-range" data-range>
                        <label class="ss-field">
                            <span>Shift</span>
                            <select class="form-select" data-range-kind data-native aria-label="Shift">
                                <option value="day">Day</option>
                                <option value="night">Night</option>
                            </select>
                        </label>
                        <label class="ss-field ss-field-range">
                            <span>Dates</span>
                            <input type="hidden" data-from value="">
                            <input type="hidden" data-to value="">
                            <input class="form-control" type="text" data-range-pick readonly placeholder="Select dates" aria-label="Dates">
                        </label>
                        <button class="ib" type="button" data-range-remove title="Remove" aria-label="Remove"><x-i name="close" :size="14" /></button>
                    </div>
                </template>
                <div class="ss-actions">
                    <button class="btn" type="button" data-range-add @if($dayAll || $nightAll) hidden @endif>Add dates</button>
                    <button class="btn pr" type="submit" data-shift-save>Save this month</button>
                </div>
                <p class="ss-hint">All days starts on the first of this month and continues until a later month is saved. Or add ranges that cover every date once. A date already used on the other row stays hidden.</p>
            </form>
        @else
            <p class="ss-hint">You can view this shift. Super Admin, HR Admin, or the parent assigns it.</p>
        @endif
    </div>
</section>
<script>
(function () {
    if (window.empShiftReady) return;
    window.empShiftReady = true;

    function shiftKey(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function shiftSpan(from, to) {
        const keys = {};
        if (!from && !to) return keys;
        const start = from && to ? (from < to ? from : to) : (from || to);
        const end = from && to ? (from < to ? to : from) : (from || to);
        let cursor = new Date(start + 'T00:00:00');
        const last = new Date(end + 'T00:00:00');
        while (cursor <= last) {
            keys[shiftKey(cursor)] = true;
            cursor.setDate(cursor.getDate() + 1);
        }
        return keys;
    }

    function rowBounds(other) {
        const pick = other.querySelector('[data-range-pick]');
        if (pick && pick._flatpickr && pick._flatpickr.selectedDates.length) {
            const fp = pick._flatpickr;
            const start = fp.formatDate(fp.selectedDates[0], 'Y-m-d');
            const end = fp.selectedDates.length > 1 ? fp.formatDate(fp.selectedDates[1], 'Y-m-d') : start;
            return [start, end];
        }
        const raw = (pick && pick.value ? pick.value : '').trim();
        const bits = raw.split(/\s+to\s+|\s+[–—-]\s+/i).map(function (part) { return part.trim(); }).filter(Boolean);
        if (bits.length && /^\d{4}-\d{2}-\d{2}$/.test(bits[0])) {
            return [bits[0], bits[1] && /^\d{4}-\d{2}-\d{2}$/.test(bits[1]) ? bits[1] : bits[0]];
        }
        const from = other.querySelector('[data-from]');
        const to = other.querySelector('[data-to]');
        if (from && from.value) return [from.value, (to && to.value) || from.value];
        return ['', ''];
    }

    function shiftTaken(root, row) {
        const taken = {};
        root.querySelectorAll('[data-range]').forEach(function (other) {
            if (other === row) return;
            const bounds = rowBounds(other);
            const dates = shiftSpan(bounds[0], bounds[1]);
            Object.keys(dates).forEach(function (key) { taken[key] = true; });
        });
        return taken;
    }

    function placeShiftCalendar(instance) {
        const anchor = instance.altInput || instance.input;
        const box = anchor.getBoundingClientRect();
        const cal = instance.calendarContainer;
        const width = cal.offsetWidth || 308;
        const height = cal.offsetHeight || 320;
        let top = box.bottom + 6;
        if (top + height > window.innerHeight - 8) top = Math.max(8, box.top - height - 6);
        let left = box.left;
        if (left + width > window.innerWidth - 8) left = Math.max(8, window.innerWidth - width - 8);
        cal.style.position = 'fixed';
        cal.style.top = top + 'px';
        cal.style.left = left + 'px';
        cal.style.right = 'auto';
        cal.style.zIndex = '80';
    }

    window.closeShiftCalendars = function (root) {
        if (!root) return;
        root.querySelectorAll('[data-range-pick]').forEach(function (input) {
            if (input._flatpickr) input._flatpickr.destroy();
        });
    };

    window.bindShiftDates = function (root) {
        if (!root || typeof flatpickr !== 'function') return;
        const host = root.id === 'empShift' ? root : root.closest('#empShift');
        if (!host) return;
        root.querySelectorAll('[data-range-pick]').forEach(function (input) {
            if (input.dataset.picked) return;
            input.dataset.picked = '1';
            const row = input.closest('[data-range]');
            const from = row.querySelector('[data-from]');
            const to = row.querySelector('[data-to]');
            const initial = [];
            if (from.value) initial.push(from.value);
            if (to.value && to.value !== from.value) initial.push(to.value);
            flatpickr(input, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd M Y',
                conjunction: ' to ',
                altInputClass: 'in form-control',
                allowInput: false,
                disableMobile: true,
                static: false,
                appendTo: document.body,
                clickOpens: true,
                position: function (instance) {
                    placeShiftCalendar(instance);
                },
                minDate: host.dataset.start,
                maxDate: host.dataset.end,
                defaultDate: initial,
                onReady: function (selected, value, instance) {
                    instance.calendarContainer.classList.add('ss-cal');
                },
                onOpen: function (selected, value, instance) {
                    instance.calendarContainer.classList.add('ss-cal');
                },
                onMonthChange: function (selected, value, instance) {
                    placeShiftCalendar(instance);
                },
                onYearChange: function (selected, value, instance) {
                    placeShiftCalendar(instance);
                },
                disable: [function (date) {
                    return !!shiftTaken(host, row)[shiftKey(date)];
                }],
                onDayCreate: function (dObj, dStr, instance, dayElem) {
                    const date = dayElem.dateObj;
                    if (!date || !shiftTaken(host, row)[shiftKey(date)]) return;
                    dayElem.classList.add('flatpickr-disabled', 'ss-used');
                    dayElem.style.visibility = 'hidden';
                    dayElem.setAttribute('aria-hidden', 'true');
                },
                onChange: function (selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const start = instance.formatDate(selectedDates[0], 'Y-m-d');
                        const end = instance.formatDate(selectedDates[1], 'Y-m-d');
                        const taken = shiftTaken(host, row);
                        if (Object.keys(shiftSpan(start, end)).some(function (day) { return taken[day]; })) {
                            instance.clear();
                            from.value = '';
                            to.value = '';
                            window.toast('Those dates are already on another shift. Change that row.', 'er');
                            window.paintEmpShift(host);
                            return;
                        }
                    }
                    from.value = selectedDates[0] ? instance.formatDate(selectedDates[0], 'Y-m-d') : '';
                    to.value = selectedDates[1] ? instance.formatDate(selectedDates[1], 'Y-m-d') : (selectedDates[0] ? from.value : '');
                    window.paintEmpShift(host);
                },
                onClose: function (selectedDates, dateStr, instance) {
                    if (selectedDates.length === 1) instance.setDate([selectedDates[0], selectedDates[0]], true);
                }
            });
        });
    };

    window.paintEmpShift = function (root) {
        if (!root || root.dataset.locked === '1') return;
        const dayOn = root.querySelector('[data-kind-on="day"]');
        const nightOn = root.querySelector('[data-kind-on="night"]');
        const dayAll = root.querySelector('[data-all="day"]');
        const nightAll = root.querySelector('[data-all="night"]');
        const ranges = root.querySelector('[data-ranges]');
        const add = root.querySelector('[data-range-add]');
        const hidden = root.querySelector('[data-continuous-value]');
        const note = root.querySelector('[data-shift-note]');
        const save = root.querySelector('[data-shift-save]');
        const days = Number(root.dataset.days);
        const start = root.dataset.start;
        const continuous = dayAll.checked ? 'day' : (nightAll.checked ? 'night' : '');
        hidden.value = continuous;

        root.querySelector('[data-shift-card="day"]').classList.toggle('is-off', !dayOn.checked);
        root.querySelector('[data-shift-card="night"]').classList.toggle('is-off', !nightOn.checked);
        root.querySelector('[data-kind-label="day"]').textContent = dayOn.checked ? 'On' : 'Off';
        root.querySelector('[data-kind-label="night"]').textContent = nightOn.checked ? 'On' : 'Off';
        dayAll.disabled = !dayOn.checked;
        nightAll.disabled = !nightOn.checked;
        root.querySelector('[data-shift-card="day"]').querySelectorAll('[data-kind-on], [data-all]').forEach(function (input) {
            input.disabled = nightAll.checked;
        });
        root.querySelector('[data-shift-card="night"]').querySelectorAll('[data-kind-on], [data-all]').forEach(function (input) {
            input.disabled = dayAll.checked;
        });
        if (dayOn.checked && !nightAll.checked) dayOn.disabled = false;
        if (nightOn.checked && !dayAll.checked) nightOn.disabled = false;
        ranges.hidden = continuous !== '';
        root.querySelectorAll('[data-range] select, [data-range] button').forEach(function (input) {
            input.disabled = continuous !== '';
        });

        const covered = {};
        let overlap = false;
        if (continuous === 'day' || continuous === 'night') {
            for (let i = 0; i < days; i++) covered[i] = continuous;
        } else {
            root.querySelectorAll('[data-range]').forEach(function (row) {
                const kind = row.querySelector('[data-range-kind]').value;
                const on = root.querySelector('[data-kind-on="' + kind + '"]');
                if (!on || !on.checked) return;
                const from = row.querySelector('[data-from]').value;
                const to = row.querySelector('[data-to]').value;
                if (!from || !to || from < start || to > root.dataset.end || to < from) return;
                let cursor = new Date(from + 'T00:00:00');
                const last = new Date(to + 'T00:00:00');
                while (cursor <= last) {
                    const key = cursor.getFullYear() + '-' + String(cursor.getMonth() + 1).padStart(2, '0') + '-' + String(cursor.getDate()).padStart(2, '0');
                    const index = Math.round((cursor - new Date(start + 'T00:00:00')) / 86400000);
                    if (covered[index]) overlap = true;
                    covered[index] = kind;
                    cursor.setDate(cursor.getDate() + 1);
                }
            });
        }

        let open = 0;
        root.querySelectorAll('[data-cell]').forEach(function (cell, index) {
            const kind = covered[index];
            cell.classList.remove('is-day', 'is-night', 'is-open', 'is-soft');
            if (!kind) {
                open++;
                cell.classList.add('is-open');
            } else {
                cell.classList.add(kind === 'night' ? 'is-night' : 'is-day');
            }
        });

        if (continuous === 'day' || continuous === 'night') {
            note.textContent = 'All days use ' + (continuous === 'night' ? 'Night' : 'Day') + '. The other shift stays off.';
        } else if (overlap) {
            note.textContent = 'These dates are on Day and Night together. Each date needs one shift.';
        } else if (open > 0) {
            note.textContent = open + (open === 1 ? ' day is' : ' days are') + ' still open. Fill them before saving.';
        } else {
            note.textContent = 'Every day in this month has one shift.';
        }
        if (save) save.disabled = continuous === '' && (open > 0 || overlap);
        if (add) add.hidden = continuous !== '' || open === 0;
        root.querySelectorAll('[data-range-pick]').forEach(function (input) {
            if (input._flatpickr && input._flatpickr.isOpen) input._flatpickr.redraw();
        });
    };

    document.addEventListener('change', function (event) {
        const root = event.target.closest('#empShift');
        if (!root || root.dataset.locked === '1') return;
        const dayOn = root.querySelector('[data-kind-on="day"]');
        const nightOn = root.querySelector('[data-kind-on="night"]');
        const dayAll = root.querySelector('[data-all="day"]');
        const nightAll = root.querySelector('[data-all="night"]');
        if (!dayOn || !event.target.matches('[data-kind-on], [data-all], [data-from], [data-to], [data-range-kind]')) return;
        if (!dayOn.checked && !nightOn.checked) {
            event.target.checked = true;
            window.toast('Turn Day or Night on. Both cannot be off.', 'er');
        } else if (event.target.matches('[data-kind-on="day"]') && !dayOn.checked) {
            nightOn.checked = true;
            nightAll.checked = true;
            dayAll.checked = false;
        } else if (event.target.matches('[data-kind-on="night"]') && !nightOn.checked) {
            dayOn.checked = true;
            dayAll.checked = true;
            nightAll.checked = false;
        } else if (event.target.matches('[data-all="day"]') && dayAll.checked) {
            dayOn.checked = true;
            nightOn.checked = false;
            nightAll.checked = false;
        } else if (event.target.matches('[data-all="night"]') && nightAll.checked) {
            nightOn.checked = true;
            dayOn.checked = false;
            dayAll.checked = false;
        } else if (event.target.matches('[data-all]') && !event.target.checked) {
            dayOn.checked = true;
            nightOn.checked = true;
        }
        window.paintEmpShift(root);
    });

    document.addEventListener('click', function (event) {
        const nav = event.target.closest('[data-shift-nav]');
        if (nav) {
            const root = nav.closest('#empShift');
            if (!root) return;
            $.ajax({
                url: root.dataset.load,
                method: 'GET',
                data: { year: nav.dataset.year, month: nav.dataset.month, partial: 1 },
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (body) {
                window.closeShiftCalendars(root);
                root.outerHTML = body.html;
                const next = document.getElementById('empShift');
                window.bindShiftDates(next);
                window.paintEmpShift(next);
            }).fail(function () {
                window.toast('Could not open that month', 'er');
            });
            return;
        }
        const add = event.target.closest('[data-range-add]');
        if (add) {
            const root = add.closest('#empShift');
            const list = root.querySelector('[data-ranges]');
            const template = root.querySelector('[data-range-template]');
            if (!list || !template) return;
            const copy = template.content.firstElementChild.cloneNode(true);
            list.appendChild(copy);
            list.querySelectorAll('[data-range]').forEach(function (item, itemIndex) {
                const kind = item.querySelector('[data-range-kind]');
                const from = item.querySelector('[data-from]');
                const to = item.querySelector('[data-to]');
                kind.name = 'ranges[' + itemIndex + '][kind]';
                from.name = 'ranges[' + itemIndex + '][starts_on]';
                to.name = 'ranges[' + itemIndex + '][ends_on]';
            });
            window.bindShiftDates(copy);
            window.paintEmpShift(root);
            const opened = copy.querySelector('[data-range-pick]');
            if (opened && opened._flatpickr) opened._flatpickr.open();
            return;
        }
        const remove = event.target.closest('[data-range-remove]');
        if (remove) {
            const root = remove.closest('#empShift');
            const list = root.querySelector('[data-ranges]');
            if (list.querySelectorAll('[data-range]').length < 2) {
                window.toast('Keep at least one date row.', 'er');
                return;
            }
            const row = remove.closest('[data-range]');
            const pick = row.querySelector('[data-range-pick]');
            if (pick && pick._flatpickr) pick._flatpickr.destroy();
            row.remove();
            list.querySelectorAll('[data-range]').forEach(function (item, itemIndex) {
                item.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace(/ranges\[\d+]/, 'ranges[' + itemIndex + ']');
                });
            });
            window.paintEmpShift(root);
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form || form.id !== 'empShiftForm') return;
        event.preventDefault();
        const root = form.closest('#empShift');
        const button = form.querySelector('[data-shift-save]');
        if (button) button.disabled = true;
        $.ajax({
            url: form.action,
            method: 'POST',
            data: $(form).serialize(),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (body) {
            window.closeShiftCalendars(root);
            root.outerHTML = body.html;
            const next = document.getElementById('empShift');
            window.bindShiftDates(next);
            window.paintEmpShift(next);
            window.toast(body.message);
        }).fail(function (xhr) {
            const errors = xhr.responseJSON && xhr.responseJSON.errors;
            const message = errors ? Object.values(errors)[0][0] : 'Could not save this shift';
            window.toast(message, 'er');
            if (button) button.disabled = false;
        });
    });

    const first = document.getElementById('empShift');
    window.bindShiftDates(first);
    window.paintEmpShift(first);
})();
</script>
