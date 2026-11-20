@extends('layouts.app')
@section('title', 'Advances & Deductions')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Advances & Deductions') }}</h1>
            <p>An advance is deducted in full from the salary of the month it is given.</p>
        </div>
    </div>

    @include('operations.errors')

    <div class="ad-stats g c3">
        <div class="card kp">
            <small>Active</small>
            <b id="adStatActive">{{ $stats['active'] }}</b>
            <span>Waiting for that month’s payroll</span>
        </div>
        <div class="card kp">
            <small>To deduct</small>
            <b id="adStatBalance">₹{{ number_format($stats['balance'], 2) }}</b>
            <span>Full amount, from that month’s salary</span>
        </div>
        <div class="card kp">
            <small>Deducted</small>
            <b id="adStatDeducted">{{ $stats['deducted'] }}</b>
            <span>Already taken in payroll</span>
        </div>
    </div>

    @if($canEdit)
        <section class="card ad-form-card">
            <header>
                <div>
                    <h3>{{ __('New advance') }}</h3>
                    <p>The full amount is deducted when a salary sheet covering this date is approved.</p>
                </div>
            </header>
            <form id="advanceForm" class="ad-form" method="post" action="{{ route('advances.store') }}">
                @csrf
                <label class="ad-employee">
                    <span>Employee</span>
                    <select class="in" name="employee_id" required>
                        <option value="">Select employee</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->full_name }} · {{ $employee->employee_code }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ad-title-field">
                    <span>Title</span>
                    <input class="in" name="title" required maxlength="120" placeholder="Salary advance">
                </label>
                <label>
                    <span>Amount</span>
                    <input class="in" type="number" name="amount" min="1" step="0.01" required placeholder="0.00">
                </label>
                <div class="ad-form-actions">
                    <button class="btn pr" type="submit">Add advance</button>
                </div>
            </form>
        </section>
    @endif

    <section class="card ad" id="advanceList">
        <form class="tbar ad-bar" id="advanceFilters" method="get" action="{{ route('advances.index') }}">
            <div class="sr ad-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search employee or title" autocomplete="off">
            </div>
            <select class="in" name="status" data-native>
                <option value="">All statuses</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="completed" @selected($filters['status'] === 'completed')>Deducted</option>
                <option value="closed" @selected($filters['status'] === 'closed')>Closed</option>
            </select>
            <div class="sp"></div>
            <span class="ad-count" id="adCount">{{ $advances->total() }} {{ \Illuminate\Support\Str::plural('advance', $advances->total()) }}</span>
        </form>
        <div id="advanceResults">
            @include('operations.advances._list')
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function ($) {
    const root = $('#advanceList');
    const filters = $('#advanceFilters');
    const create = $('#advanceForm');
    if (!root.length || !filters.length) return;

    const phone = () => window.matchMedia('(max-width: 700px)').matches;
    const token = $('meta[name="csrf-token"]').attr('content');
    let timer = null;
    let req = null;
    let loadingMore = false;

    function query(extra) {
        const params = new URLSearchParams();
        filters.serializeArray().forEach(function (item) {
            if (String(item.value || '').trim() !== '') params.set(item.name, item.value);
        });
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                if (extra[key] === null || extra[key] === '') params.delete(key);
                else params.set(key, extra[key]);
            });
        }
        params.delete('partial');
        return params;
    }

    function paint(node) {
        const active = document.getElementById('adStatActive');
        const deducted = document.getElementById('adStatDeducted');
        const balance = document.getElementById('adStatBalance');
        const count = document.getElementById('adCount');
        if (active) active.textContent = node.getAttribute('data-active') || '0';
        if (deducted) deducted.textContent = node.getAttribute('data-deducted') || '0';
        if (balance) balance.textContent = '₹' + (node.getAttribute('data-balance') || '0.00');
        if (count) {
            const total = Number(node.getAttribute('data-total') || 0);
            count.textContent = total + (total === 1 ? ' advance' : ' advances');
        }
    }

    function load(extra, append) {
        if (req) req.abort();
        const params = query(extra);
        const url = filters.attr('action') + (params.toString() ? '?' + params.toString() : '');
        const current = req = $.ajax({
            url: url,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { partial: 1 }
        }).done(function (html) {
            const incoming = document.createElement('div');
            incoming.innerHTML = html;
            const next = incoming.querySelector('.ad-body');
            const host = document.querySelector('#advanceResults .ad-body');
            if (!next || !host) return;
            if (append) {
                const cards = host.querySelector('.ad-cards');
                next.querySelectorAll('.ad-card').forEach(function (card) {
                    if (cards) cards.appendChild(card);
                });
                const body = host.querySelector('tbody');
                next.querySelectorAll('tbody tr').forEach(function (row) {
                    if (body && !row.querySelector('.ad-empty')) body.appendChild(row);
                });
                host.dataset.page = next.dataset.page;
                host.dataset.last = next.dataset.last;
                const pager = host.querySelector('.pgn');
                const nextPager = next.querySelector('.pgn');
                if (pager && nextPager) pager.replaceWith(nextPager);
            } else {
                host.replaceWith(next);
                paint(next);
                if (window.history.replaceState) window.history.replaceState({}, '', url);
            }
        }).always(function () {
            if (req !== current) return;
            loadingMore = false;
            req = null;
        });
    }

    function failMessage(xhr, fallback) {
        const errors = xhr.responseJSON && xhr.responseJSON.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first && first[0]) return first[0];
        }
        return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
    }

    filters.on('submit', function (event) {
        event.preventDefault();
        load({ page: 1 });
    });
    filters.on('input', 'input[name="q"]', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { load({ page: 1 }); }, 280);
    });
    filters.on('change', 'select[name="status"]', function () {
        load({ page: 1 });
    });
    root.on('click', '.pgn a', function (event) {
        event.preventDefault();
        const url = new URL(this.href, window.location.origin);
        load({ page: url.searchParams.get('page') || 1 });
    });
    root.on('click', '.ad-close', function () {
        const button = this;
        const go = function () {
            $.ajax({
                url: button.getAttribute('data-url'),
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                data: { _token: token }
            }).done(function (payload) {
                window.toast((payload && payload.message) || 'Advance closed.');
                load();
            }).fail(function (xhr) {
                window.toast(failMessage(xhr, 'Could not close this advance.'), 'er');
            });
        };
        if (window.askConfirm) {
            window.askConfirm(@json(__('Close this advance? It will not be deducted from salary.')), { label: @json(__('Close')), danger: true }).then(function (ok) {
                if (ok) go();
            });
            return;
        }
        go();
    });

    if (create.length) {
        create.on('submit', function (event) {
            event.preventDefault();
            $.ajax({
                url: create.attr('action'),
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                data: create.serialize()
            }).done(function (payload) {
                window.toast((payload && payload.message) || 'Advance recorded.');
                create[0].reset();
                create.find('select').val('').trigger('change');
                load({ page: 1 });
            }).fail(function (xhr) {
                window.toast(failMessage(xhr, 'Could not record this advance.'), 'er');
            });
        });
    }

})(jQuery);
</script>
@endpush
