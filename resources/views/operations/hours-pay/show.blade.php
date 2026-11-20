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

    function paintNightSunday(scope) {
        const box = scope.querySelector('[data-night-sunday]');
        if (!box) return;
        const on = box.checked;
        scope.querySelectorAll('[data-night-sunday-times]').forEach((el) => { el.hidden = !on; });
        scope.querySelectorAll('[data-night-sunday-off]').forEach((el) => { el.hidden = on; });
        scope.querySelectorAll('[data-night-sunday-times] input').forEach((input) => {
            input.disabled = !on;
            input.required = on;
        });
        const label = scope.querySelector('[data-night-sunday-label]');
        if (label) label.textContent = on ? 'On' : 'Off';
    }

    function paintMode(scope) {
        const own = scope.querySelector('[data-own-hours]');
        const selected = scope.querySelector('input[name="mode"]:checked');
        if (!own || !selected) return;
        const custom = selected.value === 'own';
        own.hidden = !custom;
        own.querySelectorAll('input').forEach((field) => {
            if (field.closest('[data-night-sunday-times]') || field.name === 'works_sunday') return;
            field.disabled = false;
            field.required = custom && field.type === 'time' && field.name.indexOf('break') === -1;
        });
        paintNightSunday(scope);
        if (!custom) {
            own.querySelectorAll('[data-night-sunday-times] input').forEach((field) => {
                field.required = false;
                field.disabled = true;
            });
        }
    }

    function bind(scope) {
        paintMode(scope);
        pickDates(scope);
    }

    root.addEventListener('change', function (event) {
        if (event.target.name === 'mode' || event.target.matches('[data-night-sunday]')) paintMode(root);
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
