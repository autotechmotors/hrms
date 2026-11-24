@extends('layouts.app')
@section('title', 'Advances & Deductions')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Advances & Deductions') }}</h1>
            <p>Create an advance request. The accountant approves it, or rejects it with a reason. Only approved advances deduct from salary.</p>
        </div>
    </div>

    @include('operations.errors')

    <div class="ad-stats g c4">
        <div class="card kp">
            <small>Pending</small>
            <b id="adStatPending">{{ $stats['pending'] }}</b>
            <span>Waiting for the accountant</span>
        </div>
        <div class="card kp">
            <small>Approved</small>
            <b id="adStatActive">{{ $stats['active'] }}</b>
            <span>Waiting for that month’s payroll</span>
        </div>
        <div class="card kp">
            <small>To deduct</small>
            <b id="adStatBalance">₹{{ number_format($stats['balance'], 2) }}</b>
            <span>Approved amount still open</span>
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
                    <p>Sends a request to the accountant. After approval, the full amount deducts when a salary sheet covering this date is approved.</p>
                </div>
            </header>
            <form id="advanceForm" class="ad-form" method="post" action="{{ route('advances.store') }}">
                @csrf
                <div class="ad-employee">
                    <span>Employee</span>
                    <div class="ad-employee-field">
                        <select name="employee_id" id="adEmployee" class="ad-pick" data-native required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} · {{ $employee->employee_code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label class="ad-title-field">
                    <span>Title</span>
                    <input class="in" name="title" required maxlength="120" placeholder="Salary advance">
                </label>
                <label>
                    <span>Amount</span>
                    <input class="in" type="number" name="amount" min="1" step="0.01" required placeholder="0.00">
                </label>
                <div class="ad-form-actions">
                    <button class="btn pr" type="submit">Send request</button>
                </div>
            </form>
        </section>
    @endif

    @if($canEdit)
        <div class="modal fade" id="adEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="adEditForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit advance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="d-block mb-3">
                            <span>Title</span>
                            <input class="in form-control" name="title" required maxlength="120">
                        </label>
                        <label class="d-block">
                            <span>Amount</span>
                            <input class="in form-control" type="number" name="amount" min="1" step="0.01" required>
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canDecide)
        <div class="modal fade" id="adRejectModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" id="adRejectForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject advance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="d-block">
                            <span>Reason</span>
                            <textarea class="in form-control" name="reason" required minlength="3" maxlength="500" placeholder="Why is this advance rejected?" rows="3"></textarea>
                        </label>
                        <p class="ad-reject-hint mt-2 mb-0">A reason is required. The person who created the advance will see it.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn pr">Reject advance</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <section class="card ad" id="advanceList">
        <form class="tbar ad-bar" id="advanceFilters" method="get" action="{{ route('advances.index') }}">
            <div class="sr ad-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search employee or title" autocomplete="off">
            </div>
            <select class="in" name="status" data-native>
                <option value="">All statuses</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                <option value="active" @selected($filters['status'] === 'active')>Approved</option>
                <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
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

    function placeEmployeeMenu(field) {
        const shell = field.next('.select2-container')[0];
        const holder = document.querySelector('body > .select2-container--open');
        const menu = holder ? holder.querySelector('.select2-dropdown') : null;
        if (!shell || !holder || !menu) return;
        const rect = shell.getBoundingClientRect();
        holder.style.setProperty('position', 'absolute', 'important');
        holder.style.setProperty('top', (rect.bottom + window.scrollY) + 'px', 'important');
        holder.style.setProperty('left', (rect.left + window.scrollX) + 'px', 'important');
        holder.style.setProperty('width', rect.width + 'px', 'important');
        menu.classList.remove('select2-dropdown--above');
        menu.classList.add('select2-dropdown--below');
        menu.style.setProperty('width', rect.width + 'px', 'important');
        menu.style.setProperty('min-width', rect.width + 'px', 'important');
    }

    function bindEmployeeSelect() {
        const field = $('#adEmployee');
        if (!field.length || !$.fn.select2) return;
        if (field.data('select2')) {
            field.off('select2:open.adPlace');
            field.select2('destroy');
        }
        field.select2({
            width: '100%',
            dropdownParent: $(document.body),
            minimumResultsForSearch: field.find('option').length > 8 ? 0 : Infinity,
            placeholder: field.find('option[value=""]').first().text() || 'Select employee',
            allowClear: true,
            scrollAfterSelect: false,
        });
        field.on('select2:open.adPlace', function () {
            window.requestAnimationFrame(function () {
                placeEmployeeMenu(field);
                const search = document.querySelector('.select2-container--open .select2-search__field');
                if (search) search.focus({ preventScroll: true });
            });
        });
    }
    bindEmployeeSelect();

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
        const pending = document.getElementById('adStatPending');
        const active = document.getElementById('adStatActive');
        const deducted = document.getElementById('adStatDeducted');
        const balance = document.getElementById('adStatBalance');
        const count = document.getElementById('adCount');
        if (pending) pending.textContent = node.getAttribute('data-pending') || '0';
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
    root.on('click', '.ad-approve', function () {
        const url = this.getAttribute('data-url');
        const go = function () {
            $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                data: { _token: token }
            }).done(function (payload) {
                window.toast((payload && payload.message) || 'Advance approved.');
                load();
            }).fail(function (xhr) {
                window.toast(failMessage(xhr, 'Could not approve this advance.'), 'er');
            });
        };
        if (window.askConfirm) {
            window.askConfirm(@json(__('Approve this advance request?')), { label: @json(__('Approve')) }).then(function (ok) {
                if (ok) go();
            });
            return;
        }
        go();
    });

    root.on('click', '.ad-reject', function () {
        const form = $('#adRejectForm');
        if (!form.length) return;
        form.data('url', this.getAttribute('data-url'));
        form.find('[name="reason"]').val('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('adRejectModal')).show();
    });

    $('#adRejectForm').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        const reason = $.trim(form.find('[name="reason"]').val());
        if (reason.length < 3) {
            window.toast('A reason is required.', 'er');
            return;
        }
        $.ajax({
            url: form.data('url'),
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            data: { _token: token, reason: reason }
        }).done(function (payload) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adRejectModal')).hide();
            window.toast((payload && payload.message) || 'Advance rejected.');
            load();
        }).fail(function (xhr) {
            window.toast(failMessage(xhr, 'Could not reject this advance.'), 'er');
        });
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

    root.on('click', '.ad-edit', function () {
        const button = this;
        const form = $('#adEditForm');
        if (!form.length) return;
        form.data('url', button.getAttribute('data-url'));
        form.find('[name="title"]').val(button.getAttribute('data-title') || '');
        form.find('[name="amount"]').val(button.getAttribute('data-amount') || '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('adEditModal')).show();
    });

    root.on('click', '.ad-delete', function () {
        const button = this;
        const go = function () {
            $.ajax({
                url: button.getAttribute('data-url'),
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                data: { _token: token, _method: 'DELETE' }
            }).done(function (payload) {
                window.toast((payload && payload.message) || 'Advance deleted.');
                load();
            }).fail(function (xhr) {
                window.toast(failMessage(xhr, 'Could not delete this advance.'), 'er');
            });
        };
        if (window.askConfirm) {
            window.askConfirm(@json(__('Delete this advance?')), { label: @json(__('Delete')), danger: true }).then(function (ok) {
                if (ok) go();
            });
            return;
        }
        go();
    });

    $('#adEditForm').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        $.ajax({
            url: form.data('url'),
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            data: form.serialize() + '&_method=PUT&_token=' + encodeURIComponent(token)
        }).done(function (payload) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adEditModal')).hide();
            window.toast((payload && payload.message) || 'Advance updated.');
            load();
        }).fail(function (xhr) {
            window.toast(failMessage(xhr, 'Could not update this advance.'), 'er');
        });
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
                const employee = $('#adEmployee');
                if (employee.data('select2')) {
                    employee.val(null).trigger('change');
                } else {
                    employee.val('');
                }
                load({ page: 1 });
            }).fail(function (xhr) {
                window.toast(failMessage(xhr, 'Could not record this advance.'), 'er');
            });
        });
    }

})(jQuery);
</script>
@endpush
