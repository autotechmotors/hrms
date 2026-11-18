@extends('layouts.app')
@section('title', 'Holidays')
@section('content')
    <div class="ph">
        <div>
            <h1>Holidays</h1>
            <p>A public holiday is a paid day for everyone. No punch needed. One person’s day off pays only that person.</p>
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

    <div id="hcBody">
    @include('operations.holidays._body')
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
                            <option value="public" @selected(old('kind', 'public') === 'public')>Public — paid day for everyone</option>
                            <option value="restricted" @selected(old('kind') === 'restricted')>Restricted</option>
                            <option value="optional" @selected(old('kind') === 'optional')>Optional</option>
                        </x-select-field>
                        <p class="hc-paid-note">Paid day for everyone. No punch needed.</p>
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
                        <form id="holidayDeleteForm" method="post" action="#">
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

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const year = @json($year);

    function reloadHolidays() {
        if (!window.jQuery) return;
        window.jQuery.ajax({
            url: @json(route('holidays.index')),
            data: { year: year, partial: 1 },
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (html) {
            const body = document.getElementById('hcBody');
            if (body) body.innerHTML = html;
        });
    }

    function postForm(form, method) {
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        window.jQuery.ajax({
            url: form.action,
            method: method || 'POST',
            data: window.jQuery(form).serialize(),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }
        }).done(function (body) {
            const modal = form.closest('.modal');
            if (modal && window.bootstrap) window.bootstrap.Modal.getInstance(modal)?.hide();
            window.toast(body.message || 'Saved');
            reloadHolidays();
        }).fail(function (xhr) {
            const errors = xhr.responseJSON && xhr.responseJSON.errors;
            const message = errors ? Object.values(errors)[0][0] : 'Could not save';
            window.toast(message, 'er');
        }).always(function () {
            if (button) button.disabled = false;
        });
    }

    const addForm = document.querySelector('#holidayModal form');
    if (addForm) {
        addForm.addEventListener('submit', function (event) {
            event.preventDefault();
            postForm(addForm, 'POST');
        });
    }

    const deleteForm = document.getElementById('holidayDeleteForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!deleteForm.action || deleteForm.action.endsWith('#')) return;
            window.askConfirm('Remove this holiday? That day becomes a working day again.', { label: 'Remove', danger: true }).then(function (ok) {
                if (!ok) return;
                const button = deleteForm.querySelector('button');
                if (button) button.disabled = true;
                window.jQuery.ajax({
                    url: deleteForm.action,
                    method: 'POST',
                    data: { _method: 'DELETE', _token: token },
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).done(function (body) {
                    window.bootstrap.Modal.getInstance(viewModal)?.hide();
                    window.toast(body.message || 'Removed');
                    reloadHolidays();
                }).fail(function () {
                    window.toast('Could not remove this holiday', 'er');
                }).always(function () {
                    if (button) button.disabled = false;
                });
            });
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('#hcPersonForm');
        if (!form) return;
        event.preventDefault();
        postForm(form, 'POST');
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.hc-person-remove');
        if (!button) return;
        window.askConfirm('Remove this paid day? That person works that day again.', { label: 'Remove', danger: true }).then(function (ok) {
            if (!ok) return;
            window.jQuery.ajax({
                url: button.getAttribute('data-url'),
                method: 'POST',
                data: { _method: 'DELETE', _token: token },
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (body) {
                window.toast(body.message || 'Removed');
                reloadHolidays();
            }).fail(function () {
                window.toast('Could not remove this paid day', 'er');
            });
        });
    });
})();
</script>
@endpush
