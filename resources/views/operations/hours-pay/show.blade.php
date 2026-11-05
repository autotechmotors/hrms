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

    function paintMode(scope) {
        const own = scope.querySelector('[data-own-hours]');
        const selected = scope.querySelector('input[name="mode"]:checked');
        if (!own || !selected) return;
        const custom = selected.value === 'own';
        own.hidden = !custom;
        const field = own.querySelector('input');
        if (field) field.required = custom;
    }

    function bind(scope) {
        paintMode(scope);
        pickDates(scope);
    }

    root.addEventListener('change', function (event) {
        if (event.target.name === 'mode') paintMode(root);
    });

    root.addEventListener('submit', function (event) {
        const form = event.target.closest('form');
        if (!form) return;
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
