@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    @include('dashboard._panel')
@endsection

@push('scripts')
<script>
(function ($) {
    let req = null;

    function bindPicker() {
        const input = document.getElementById('dashDates');
        if (!input || typeof flatpickr !== 'function' || input._flatpickr) return;
        const from = document.getElementById('dashFrom');
        const to = document.getElementById('dashTo');
        flatpickr(input, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            conjunction: ' – ',
            altInputClass: 'in form-control',
            allowInput: false,
            disableMobile: true,
            maxDate: 'today',
            static: true,
            defaultDate: [from.value, to.value],
            onChange: function (selected, value, instance) {
                if (selected.length !== 2) return;
                const start = instance.formatDate(selected[0], 'Y-m-d');
                const end = instance.formatDate(selected[1], 'Y-m-d');
                if (start === from.value && end === to.value) return;
                loadDash(start, end);
            }
        });
    }

    function loadDash(from, to) {
        const url = new URL(@json(route('dashboard')), window.location.origin);
        url.searchParams.set('from', from);
        url.searchParams.set('to', to);
        url.searchParams.set('partial', '1');
        if (req) req.abort();
        const panel = document.getElementById('dashPanel');
        if (panel) panel.classList.add('is-loading');
        req = $.ajax({
            url: url.toString(),
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).done(function (html) {
            $('#dashPanel').replaceWith(html);
            const clean = new URL(url);
            clean.searchParams.delete('partial');
            history.replaceState(null, '', clean.pathname + clean.search);
            bindPicker();
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') return;
            if (panel) panel.classList.remove('is-loading');
            window.toast(@json(__('Could not load this range.')), 'er');
        });
    }

    $(document).on('click', '#dashPanel [data-dash-preset]', function (event) {
        event.preventDefault();
        const link = event.currentTarget;
        loadDash(link.dataset.from, link.dataset.to);
    });

    bindPicker();
})(jQuery);
</script>
@endpush
