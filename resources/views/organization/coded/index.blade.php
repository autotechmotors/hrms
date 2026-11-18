@extends('layouts.app')
@section('title', \Illuminate\Support\Str::plural($title))
@section('content')
    <div class="ph">
        <div>
            <h1>{{ \Illuminate\Support\Str::plural($title) }}</h1>
            <p>{{ $summary }}</p>
        </div>
        <div class="ac">
            @permission($permission.'.create')
                <a class="btn pr" href="{{ route($route.'.create') }}"><x-i name="plus" :size="14" />Add {{ $title }}</a>
            @endpermission
        </div>
    </div>

    <section class="card org" id="orgList">
        <form class="tbar org-bar" id="orgFilters" method="get" action="{{ route($route.'.index') }}">
            <div class="sr org-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or code" autocomplete="off">
            </div>
            <div class="sp"></div>
            <span class="org-count" id="orgCount">{{ $records->total() }} {{ \Illuminate\Support\Str::plural('record', $records->total()) }}</span>
        </form>
        <div id="orgResults">
            @include('organization.coded._list')
        </div>
    </section>
    @if($categoryLink)
        <p class="org-note">Employment category belongs to the designation. <a href="{{ $categoryLink }}">Manage categories</a></p>
    @endif
@endsection

@push('scripts')
<script>
(function ($) {
    const root = $('#orgList');
    const form = $('#orgFilters');
    if (!root.length || !form.length) return;

    const phone = () => window.matchMedia('(max-width: 700px)').matches;
    const token = $('meta[name="csrf-token"]').attr('content');
    let timer = null;
    let req = null;
    let loadingMore = false;

    function query(extra) {
        const params = new URLSearchParams();
        form.serializeArray().forEach(function (item) {
            if (String(item.value || '').trim() !== '') params.set(item.name, item.value);
        });
        if (extra) Object.keys(extra).forEach(function (key) { params.set(key, extra[key]); });
        params.delete('partial');
        return params;
    }

    function paintCount(node) {
        const count = document.getElementById('orgCount');
        if (!count || !node) return;
        const total = Number(node.getAttribute('data-total') || 0);
        count.textContent = total + (total === 1 ? ' record' : ' records');
    }

    function load(extra, append) {
        if (req) req.abort();
        const params = query(extra);
        const url = form.attr('action') + (params.toString() ? '?' + params.toString() : '');
        const current = req = $.ajax({
            url: url,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { partial: 1 }
        }).done(function (html) {
            const incoming = document.createElement('div');
            incoming.innerHTML = html;
            const next = incoming.querySelector('.org-body');
            const host = document.querySelector('#orgResults .org-body');
            if (!next || !host) return;
            if (append) {
                const cards = host.querySelector('.org-cards');
                next.querySelectorAll('.org-card').forEach(function (card) {
                    if (cards) cards.appendChild(card);
                });
                const body = host.querySelector('tbody');
                next.querySelectorAll('tbody tr').forEach(function (row) {
                    if (body && row.querySelector('td.org-empty') === null && !row.querySelector('.org-empty')) body.appendChild(row);
                });
                host.dataset.page = next.dataset.page;
                host.dataset.last = next.dataset.last;
                const pager = host.querySelector('.pgn');
                const nextPager = next.querySelector('.pgn');
                if (pager && nextPager) pager.replaceWith(nextPager);
            } else {
                host.replaceWith(next);
                paintCount(next);
                if (window.history.replaceState) window.history.replaceState({}, '', url);
            }
        }).always(function () {
            if (req !== current) return;
            loadingMore = false;
            req = null;
            if (phone()) requestAnimationFrame(maybeMore);
        });
    }

    function maybeMore() {
        if (!phone() || loadingMore || req) return;
        const host = document.querySelector('#orgResults .org-body');
        if (!host) return;
        const page = Number(host.dataset.page || 1);
        const last = Number(host.dataset.last || 1);
        if (page >= last) return;
        const cards = host.querySelector('.org-cards');
        if (!cards || cards.getBoundingClientRect().bottom > window.innerHeight + 420) return;
        loadingMore = true;
        load({ page: page + 1 }, true);
    }

    form.on('submit', function (event) {
        event.preventDefault();
        load();
    });
    form.on('input', 'input[name="q"]', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { load(); }, 280);
    });
    root.on('click', '.pgn a', function (event) {
        event.preventDefault();
        const url = new URL(this.href, window.location.origin);
        load({ page: url.searchParams.get('page') || 1 });
    });
    root.on('click', '[data-org-delete]', function () {
        const button = this;
        const name = button.getAttribute('data-name') || 'this record';
        window.askConfirm('Delete ' + name + '?', { label: 'Delete', danger: true }).then(function (ok) {
            if (!ok) return;
            $.ajax({
                url: button.getAttribute('data-url'),
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: { _token: token, _method: 'DELETE' }
            }).done(function (payload) {
                window.toast((payload && payload.message) || 'Deleted.');
                load();
            }).fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'This record is in use and cannot be deleted.';
                window.toast(message, 'er');
            });
        });
    });
    window.addEventListener('scroll', maybeMore, { passive: true });
    requestAnimationFrame(maybeMore);
})(jQuery);
</script>
@endpush
