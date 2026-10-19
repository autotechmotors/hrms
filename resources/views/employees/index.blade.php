@extends('layouts.app')
@section('title', 'Employees')
@section('content')
    <div class="ph">
        <div>
            <h1>Employees</h1>
            <p id="elMeta">{{ $employees->total() }} people in your scope</p>
        </div>
        <div class="ac">
            @permission('employees.import')
                <a class="btn" href="{{ route('employees.import') }}"><x-i name="dl" :size="14" />Import</a>
            @endpermission
            @permission('employees.export')
                <a class="btn" href="{{ route('employees.export') }}"><x-i name="dl" :size="14" />Export</a>
            @endpermission
            @permission('employees.create')
                <a class="btn pr" href="{{ route('employees.create') }}"><x-i name="plus" :size="14" />Add employee</a>
            @endpermission
        </div>
    </div>

    <section class="card el" id="employeeList" data-url="{{ route('employees.index') }}">
        <form class="tbar el-bar" id="employeeFilters" data-ajax-filter method="get" action="{{ route('employees.index') }}">
            <div class="sr el-search">
                <span><x-i name="search" /></span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, code, email…" autocomplete="off">
            </div>
            <select class="in" name="department_id" data-filter>
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            <select class="in" name="employment_status_id" data-filter>
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected(request('employment_status_id') == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
            <button class="btn el-reset" type="button" id="employeeReset" title="Reset filters">
                <x-i name="reset" :size="14" />Reset
            </button>
            <div class="sp"></div>
            <div class="el-toggle" role="group" aria-label="View mode">
                <button class="ib on" type="button" data-view="list" title="List view" aria-pressed="true"><x-i name="list" :size="16" /></button>
                <button class="ib" type="button" data-view="block" title="Block view" aria-pressed="false"><x-i name="grid" :size="16" /></button>
            </div>
        </form>

        <div class="el-wrap" id="employeeResults" data-view="list">
            @include('employees._list', ['employees' => $employees, 'canManage' => $canManage, 'canBlock' => $canBlock])
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function ($) {
    const root = $('#employeeList');
    if (!root.length) return;

    const form = $('#employeeFilters');
    const results = $('#employeeResults');
    const meta = $('#elMeta');
    const url = root.data('url');
    const csrf = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';
    let timer = null;
    let req = null;

    setView(localStorage.getItem('employees.view') || 'list');

    function setView(mode) {
        results.attr('data-view', mode);
        localStorage.setItem('employees.view', mode);
        form.find('[data-view]').each(function () {
            const on = $(this).data('view') === mode;
            $(this).toggleClass('on', on).attr('aria-pressed', on ? 'true' : 'false');
        });
    }

    function params(extra) {
        const data = form.serializeArray().filter((item) => String(item.value || '').trim() !== '');
        if (extra) {
            Object.keys(extra).forEach((key) => data.push({ name: key, value: extra[key] }));
        }
        return $.param(data);
    }

    function load(extra) {
        if (req) req.abort();
        results.addClass('is-loading');
        const query = params(extra);
        const nextUrl = url + (query ? '?' + query : '');

        req = $.ajax({
            url: nextUrl,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { partial: 1 },
        }).done(function (html) {
            results.html(html);
            const body = results.find('.el-body');
            const total = body.data('total');
            if (typeof total !== 'undefined') {
                meta.text(total + ' people in your scope');
            }
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', nextUrl);
            }
            bindRowActions();
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') return;
            meta.text('Could not refresh the list');
        }).always(function () {
            results.removeClass('is-loading');
            req = null;
        });
    }

    function applyBlockedState(row, blocked) {
        row.toggleClass('el-blocked', blocked);
        row.find('.el-block-label').text(blocked ? 'Blocked' : 'Active');
        row.find('.el-block-switch').attr('title', blocked ? 'Blocked — click to unblock' : 'Active — click to block');
    }

    function bindRowActions() {
        results.find('form[data-confirm]').off('submit.el').on('submit.el', function (event) {
            const formEl = this;
            if (formEl.dataset.confirmed === '1') {
                event.preventDefault();
                const action = formEl.getAttribute('action');
                const token = $(formEl).find('[name=_token]').val();
                $.ajax({
                    url: action,
                    method: 'POST',
                    data: { _token: token, _method: 'DELETE' },
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).done(function () {
                    load();
                }).fail(function (xhr) {
                    if (window.toast) toast((xhr.responseJSON && xhr.responseJSON.message) || 'Delete failed.', 'er');
                });
                return;
            }
            event.preventDefault();
            window.__elPendingDelete = formEl;
            $('#confirmText').text(formEl.dataset.confirm);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).show();
        });

        results.find('.el-block-toggle').off('change.elBlock').on('change.elBlock', function () {
            const input = $(this);
            const blocked = input.is(':checked');
            const row = input.closest('[data-employee-row]');
            input.prop('disabled', true);

            $.ajax({
                url: input.data('url'),
                method: 'POST',
                data: {
                    _token: csrf,
                    _method: 'PATCH',
                    is_blocked: blocked ? 1 : 0,
                },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).done(function (res) {
                applyBlockedState(row, !!(res && res.is_blocked));
                input.prop('checked', !!(res && res.is_blocked));
            }).fail(function (xhr) {
                input.prop('checked', !blocked);
                if (window.toast) toast((xhr.responseJSON && xhr.responseJSON.message) || 'Could not update block status.', 'er');
            }).always(function () {
                input.prop('disabled', false);
            });
        });
    }

    $('#confirmButton').on('click.elDelete', function () {
        const pending = window.__elPendingDelete;
        if (!pending) return;
        pending.dataset.confirmed = '1';
        $(pending).trigger('submit');
        window.__elPendingDelete = null;
    });

    form.on('submit', function (event) {
        event.preventDefault();
        load();
    });

    form.find('[name=q]').on('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { load(); }, 280);
    });

    form.on('change', '[data-filter]', function () {
        load();
    });

    $('#employeeReset').on('click', function () {
        form.find('[name=q]').val('');
        form.find('[data-filter]').each(function () {
            const select = $(this);
            select.val('');
            if (select.data('select2')) {
                select.trigger('change.select2');
            }
        });
        load();
    });

    form.find('[data-view]').on('click', function () {
        setView($(this).data('view'));
    });

    results.on('click', '.el-pages a', function (event) {
        event.preventDefault();
        const href = $(this).attr('href');
        if (!href) return;
        const page = new URL(href, window.location.origin).searchParams.get('page');
        load(page ? { page: page } : {});
    });

    bindRowActions();
})(jQuery);
</script>
@endpush
