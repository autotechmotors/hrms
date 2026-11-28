@extends('layouts.app')
@section('title', $employee->full_name.' — Hours & pay')
@section('content')
    <div class="ph">
        <div>
            <x-back :href="route('hours-pay.index')" label="Hours & pay" />
            <h1>{{ $employee->full_name }}</h1>
            <p>{{ $employee->employee_code }} · {{ $employee->department?->name ?? 'No department' }}</p>
        </div>
    </div>
    <div class="hp" id="hpDetail">
        @include('operations.hours-pay._detail')
    </div>
@endsection

@push('scripts')
<script>
(function ($) {
    const root = document.getElementById('hpDetail');
    if (!root) return;

    function pickDates(scope) {
        if (typeof flatpickr !== 'function') return;
        scope.querySelectorAll('input[type="date"]').forEach((input) => {
            if (input.dataset.picked) return;
            input.dataset.picked = '1';
            flatpickr(input, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd M Y',
                altInputClass: 'in form-control',
                allowInput: true,
                disableMobile: true
            });
        });
    }

    function paintSundaySwitch(scope, kind) {
        const boxes = scope.querySelectorAll('[data-' + kind + '-sunday]');
        if (!boxes.length) return;
        const box = Array.from(boxes).find((el) => !el.disabled) || boxes[0];
        const on = box.checked;
        scope.querySelectorAll('[data-' + kind + '-sunday-times]').forEach((el) => { el.hidden = !on; });
        scope.querySelectorAll('[data-' + kind + '-sunday-off]').forEach((el) => { el.hidden = on; });
        scope.querySelectorAll('[data-' + kind + '-sunday-times] input').forEach((input) => {
            if (input.closest('[hidden]')) return;
            input.disabled = !on;
            if (input.name.indexOf('break') === -1) input.required = on;
        });
        scope.querySelectorAll('[data-' + kind + '-sunday-label]').forEach((label) => {
            label.textContent = on ? 'On' : 'Off';
        });
        boxes.forEach((el) => { el.checked = on; });
    }

    function setEnabled(nodes, on) {
        nodes.forEach((field) => {
            field.disabled = !on;
            if (!on) field.required = false;
        });
    }

    function paintMode(scope) {
        const own = scope.querySelector('[data-own-hours]');
        const preview = scope.querySelector('[data-dept-preview]');
        const selected = scope.querySelector('input[name="mode"]:checked');
        if (!own || !selected) return;
        const custom = selected.value === 'own';
        own.hidden = !custom;
        if (preview) preview.hidden = custom;

        setEnabled(scope.querySelectorAll('[data-dept-sunday-field]'), !custom);
        setEnabled(scope.querySelectorAll('[data-own-sunday-field]'), custom);

        own.querySelectorAll('input').forEach((field) => {
            if (field.matches('[data-own-sunday-field]') || field.closest('[data-day-sunday-times]') || field.closest('[data-night-sunday-times]')) return;
            field.disabled = false;
            field.required = custom && field.type === 'time' && field.name.indexOf('break') === -1;
        });

        if (!custom) {
            own.querySelectorAll('input[type="time"]').forEach((field) => {
                field.required = false;
                field.disabled = true;
            });
        }

        paintSundaySwitch(scope, 'day');
        paintSundaySwitch(scope, 'night');

        if (custom) {
            const dayOn = scope.querySelector('[data-own-hours] [data-day-sunday]');
            const nightOn = scope.querySelector('[data-own-hours] [data-night-sunday]');
            scope.querySelectorAll('[data-own-hours] [data-day-sunday-times] input').forEach((input) => {
                const on = !!(dayOn && dayOn.checked);
                input.disabled = !on;
                if (input.name.indexOf('break') === -1) input.required = on;
            });
            scope.querySelectorAll('[data-own-hours] [data-night-sunday-times] input').forEach((input) => {
                const on = !!(nightOn && nightOn.checked);
                input.disabled = !on;
                input.required = on;
            });
        }
    }

    function bind(scope) {
        paintMode(scope);
        pickDates(scope);
    }

    root.addEventListener('change', function (event) {
        if (event.target.name === 'mode' || event.target.matches('[data-day-sunday], [data-night-sunday]')) {
            if (event.target.matches('[data-day-sunday], [data-night-sunday]')) {
                const kind = event.target.matches('[data-day-sunday]') ? 'day' : 'night';
                const on = event.target.checked;
                root.querySelectorAll('[data-' + kind + '-sunday]').forEach((el) => { el.checked = on; });
            }
            paintMode(root);
        }
    });

    root.addEventListener('submit', function (event) {
        const form = event.target.closest('form');
        if (!form || form.id === 'empShiftForm') return;
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        $.ajax({
            url: form.action,
            method: 'POST',
            data: $(form).serialize(),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (body) {
            if (window.closeShiftCalendars) window.closeShiftCalendars(root);
            root.innerHTML = body.html;
            bind(root);
            if (window.bindShiftDates) window.bindShiftDates(document.getElementById('empShift'));
            if (window.paintEmpShift) window.paintEmpShift(document.getElementById('empShift'));
            window.toast(body.message);
        }).fail(function (xhr) {
            const errors = xhr.responseJSON && xhr.responseJSON.errors;
            const message = errors ? Object.values(errors)[0][0] : 'Could not save';
            window.toast(message, 'er');
        }).always(function () {
            if (button) button.disabled = false;
        });
    });

    bind(root);
})(jQuery);
</script>
@endpush
