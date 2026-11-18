@extends('layouts.app')
@section('title', 'Reports')
@section('content')
    <div class="ph">
        <div>
            <h1>Reports</h1>
            <p>Month counts for the people you can see. Present, absent, holidays, and salary cutoff use the same clocks as pay.</p>
        </div>
    </div>
    <div id="rpBody" class="rp-page">
        @include('operations.reports._body')
    </div>
@endsection

@push('scripts')
<script>
(function ($) {
    const root = document.getElementById('rpBody');
    if (!root) return;
    const url = @json(route('reports.index'));
    let req = null;

    function load() {
        const form = document.getElementById('rpFilters');
        if (!form) return;
        if (req) req.abort();
        const next = url + '?' + $(form).serialize();
        req = $.ajax({
            url: next,
            data: { partial: 1 },
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (html) {
            root.innerHTML = html;
            if (window.history && window.history.replaceState) window.history.replaceState({}, '', next);
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') return;
            window.toast('Could not load this month', 'er');
        }).always(function () { req = null; });
    }

    root.addEventListener('change', function (event) {
        if (event.target.closest('#rpFilters')) load();
    });
    root.addEventListener('submit', function (event) {
        if (!event.target.closest('#rpFilters')) return;
        event.preventDefault();
        load();
    });
})(jQuery);
</script>
@endpush
