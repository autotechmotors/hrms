@extends('layouts.app')
@section('title', 'Employees')
@section('content')
    <div class="ph">
        <div>
            <h1>{{ __('Employees') }}</h1>
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
            <div class="el-find">
                <div class="sr el-search">
                    <span><x-i name="search" /></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, code, email…" autocomplete="off">
                </div>
                <button class="btn el-filter-open" id="elFilterOpen" type="button" aria-controls="elFilterSheet" aria-expanded="false" aria-label="Filter">
                    <x-i name="set" :size="18" />
                </button>
            </div>
            <div class="el-sheet" id="elFilterSheet">
                <div class="el-sheet-bar">
                    <b>Filters</b>
                    <button class="ib" id="elFilterClose" type="button" aria-label="Close filters">×</button>
                </div>
                <select class="in" name="department_id" data-filter form="employeeFilters">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                <select class="in" name="employment_status_id" data-filter form="employeeFilters">
                    <option value="">All statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->id }}" @selected(request('employment_status_id') == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
                <div class="el-sheet-actions">
                    <button class="btn el-reset" type="button" id="employeeReset" title="Reset filters">
                        <x-i name="reset" :size="14" />Reset
                    </button>
                    <button class="btn pr" id="elFilterApply" type="button">Apply</button>
                </div>
            </div>
            <div class="el-sheet-bg" id="elFilterBg"></div>
            <select class="in el-per" name="per_page" data-filter data-native aria-label="Employees per page">
                @foreach ([12, 24, 48] as $size)
                    <option value="{{ $size }}" @selected(($perPage ?? 12) === $size)>{{ $size }} / page</option>
                @endforeach
            </select>
            <div class="sp"></div>
            <div class="el-toggle" role="group" aria-label="View mode">
                <button class="ib on" type="button" data-view="list" title="List view" aria-pressed="true"><x-i name="list" :size="16" /></button>
                <button class="ib" type="button" data-view="block" title="Block view" aria-pressed="false"><x-i name="grid" :size="16" /></button>
            </div>
        </form>

        <div class="el-wrap" id="employeeResults" data-view="list">
            @include('employees._list', ['employees' => $employees, 'canEdit' => $canEdit, 'canDelete' => $canDelete, 'canBlock' => $canBlock])
        </div>
    </section>

    <div class="modal fade" id="credentialMachineModal" tabindex="-1" aria-labelledby="credentialMachineTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="credentialMachineTitle">{{ __('Choose a terminal') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="cred-machine-error" id="credentialMachineError" hidden></p>
                    <div class="cred-machines" id="credentialMachineList">
                        @forelse($terminals as $terminal)
                            @php
                                $role = match ($terminal->punch_role) {
                                    'in' => 'In',
                                    'out' => 'Out',
                                    default => 'In and out',
                                };
                                $online = $terminal->status === 'online';
                            @endphp
                            <button type="button" class="cred-machine {{ $online ? 'is-online' : 'is-offline' }}" data-device="{{ $terminal->id }}" data-name="{{ $terminal->name }}" data-online="{{ $online ? '1' : '0' }}">
                                <span class="cred-machine-top">
                                    <b>{{ $terminal->name }}</b>
                                    <em class="cred-machine-state">{{ $online ? __('Online') : __('Offline') }}</em>
                                </span>
                                <small>{{ $role }} · {{ $terminal->ip_address }}</small>
                            </button>
                        @empty
                            <p class="ed-empty">{{ __('Add a face terminal before collecting a credential.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
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

    const phone = function () { return window.matchMedia('(max-width: 700px)').matches; };
    let loadingMore = false;

    setView(localStorage.getItem('employees.view') || 'list');

    function setView(mode) {
        if (phone()) mode = 'block';
        results.attr('data-view', mode);
        if (!phone()) localStorage.setItem('employees.view', mode);
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

    function load(extra, append) {
        if (req) req.abort();
        if (!append) results.addClass('is-loading');
        const query = params(extra);
        const nextUrl = url + (query ? '?' + query : '');

        const current = req = $.ajax({
            url: nextUrl,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: { partial: 1 },
        }).done(function (html) {
            const incoming = $('<div>').html(html);
            const body = incoming.find('.el-body');
            if (append) {
                results.find('.el-blocks').append(body.find('.el-card'));
                results.find('tbody').append(body.find('tbody').children());
                const host = results.find('.el-body');
                host.attr('data-page', body.attr('data-page'));
                host.attr('data-last', body.attr('data-last'));
                results.find('.el-pgn').replaceWith(body.find('.el-pgn'));
            } else {
                results.html(html);
            }
            const shown = results.find('.el-body');
            const total = shown.attr('data-total');
            if (typeof total !== 'undefined') {
                meta.text(total + ' people in your scope');
            }
            if (!append && window.history && window.history.replaceState) {
                window.history.replaceState({}, '', nextUrl);
            }
            bindRowActions();
            paintFilterMark();
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') return;
            meta.text('Could not refresh the list');
        }).always(function () {
            if (req !== current) return;
            results.removeClass('is-loading');
            loadingMore = false;
            req = null;
        });
    }

    results.on('hrms:list-updated', function () {
        bindRowActions();
    });

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
                const modal = document.getElementById('confirmModal');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).hide();
                }
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
                    if (xhr.status === 404) load();
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

        results.find('.el-door-toggle').off('change.elDoor').on('change.elDoor', function () {
            const input = $(this);
            const on = input.is(':checked');
            const field = input.data('field');
            const row = input.closest('[data-employee-row]');
            const payload = { _token: csrf, _method: 'PATCH' };
            payload[field] = on ? 1 : 0;
            input.prop('disabled', true);

            $.ajax({
                url: input.data('url'),
                method: 'POST',
                data: payload,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).done(function (res) {
                const checked = !!(res && res[field]);
                input.prop('checked', checked);
                input.closest('.el-block-switch').find('.el-block-label').text(checked ? input.data('on') : input.data('off'));
                if (window.toast && res && res.message) toast(res.message, 'ok');
            }).fail(function (xhr) {
                input.prop('checked', !on);
                if (window.toast) toast((xhr.responseJSON && xhr.responseJSON.message) || 'Could not update the door.', 'er');
            }).always(function () {
                input.prop('disabled', false);
                row.find('.el-door-toggle').prop('disabled', false);
            });
        });
    }

    document.getElementById('confirmButton')?.addEventListener('click', function () {
        const pending = window.__elPendingDelete;
        if (!pending) return;
        pending.dataset.confirmed = '1';
        window.__elPendingDelete = null;
        const modal = document.getElementById('confirmModal');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
        pending.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }, true);

    form.on('submit', function (event) {
        event.preventDefault();
        load();
    });

    form.find('[name=q]').on('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { load(); }, 280);
    });

    form.on('change', '[data-filter]', function () {
        if (phone()) return;
        load();
    });

    const sheet = document.getElementById('elFilterSheet');
    const sheetBg = document.getElementById('elFilterBg');
    const sheetAnchor = form.find('.el-per').get(0);
    function placeSheet() {
        if (!sheet || !sheetAnchor) return;
        if (phone()) {
            document.body.appendChild(sheetBg);
            document.body.appendChild(sheet);
        } else if (sheet.nextElementSibling !== sheetAnchor) {
            sheetAnchor.parentNode.insertBefore(sheet, sheetAnchor);
            sheetAnchor.parentNode.insertBefore(sheetBg, sheetAnchor);
        }
    }
    placeSheet();
    let sheetSnapshot = null;
    function setSheet(open) {
        if (!sheet) return;
        if (open) {
            sheetSnapshot = {
                department_id: $(form.get(0).elements.department_id).val(),
                employment_status_id: $(form.get(0).elements.employment_status_id).val(),
            };
        }
        sheet.classList.toggle('open', open);
        if (sheetBg) sheetBg.classList.toggle('open', open);
        $('#elFilterOpen').attr('aria-expanded', open ? 'true' : 'false');
    }
    function restoreSheet() {
        if (!sheetSnapshot) return;
        $(form.get(0).elements.department_id).val(sheetSnapshot.department_id).trigger('change.select2');
        $(form.get(0).elements.employment_status_id).val(sheetSnapshot.employment_status_id).trigger('change.select2');
    }
    $('#elFilterOpen').on('click', function () { setSheet(true); });
    $('#elFilterClose, #elFilterBg').on('click', function () {
        restoreSheet();
        setSheet(false);
    });
    $('#elFilterApply').on('click', function () {
        setSheet(false);
        load();
    });
    function paintFilterMark() {
        const active = String($(form.get(0).elements.department_id).val() || '') !== '' || String($(form.get(0).elements.employment_status_id).val() || '') !== '';
        $('#elFilterOpen').toggleClass('on', active);
    }
    paintFilterMark();
    window.addEventListener('resize', function () {
        placeSheet();
        setView(localStorage.getItem('employees.view') || 'list');
    });

    $('#employeeReset').on('click', function () {
        form.find('[name=q]').val('');
        $(form.get(0).elements).filter('[data-filter]').each(function () {
            const select = $(this);
            select.val(select.attr('name') === 'per_page' ? '12' : '');
            if (select.data('select2')) {
                select.trigger('change.select2');
            }
        });
        sheetSnapshot = null;
        setSheet(false);
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

    results.on('click', 'button.ac-item', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const button = $(this);
        if (button.hasClass('wait')) return;
        if ($(event.target).closest('.ac-x').length) {
            removeCredential(button);
            return;
        }
        const url = this.getAttribute('data-url');
        const kind = this.getAttribute('data-kind');
        if (!url || !kind) return;
        const body = { kind: kind };
        if (kind === 'password') {
            const pin = window.prompt('Terminal password, 4 to 8 digits');
            if (!pin) return;
            body.password = pin;
        }
        const modal = document.getElementById('credentialMachineModal');
        const list = document.getElementById('credentialMachineList');
        if (!modal || !list || !list.querySelector('[data-device]')) {
            if (window.toast) window.toast('Add a face terminal before collecting a credential.', 'er');
            return;
        }
        const labels = { face: 'Face', finger: 'Finger', card: 'Card', password: 'Password' };
        document.getElementById('credentialMachineTitle').textContent = 'Add ' + (labels[kind] || 'credential') + ' on a terminal';
        const err = document.getElementById('credentialMachineError');
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }
        modal.dataset.url = url;
        modal.dataset.kind = kind;
        modal.dataset.password = body.password || '';
        modal.dataset.button = '';
        button.addClass('cred-pending');
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    document.getElementById('credentialMachineModal')?.addEventListener('hidden.bs.modal', function () {
        document.querySelectorAll('button.ac-item.cred-pending').forEach(function (node) {
            node.classList.remove('cred-pending');
        });
    });

    document.getElementById('credentialMachineList')?.addEventListener('click', function (event) {
        const choice = event.target.closest('[data-device]');
        const modal = document.getElementById('credentialMachineModal');
        const pending = document.querySelector('button.ac-item.cred-pending');
        const err = document.getElementById('credentialMachineError');
        if (!choice || !modal || !pending || choice.classList.contains('cred-busy')) return;
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }
        const button = $(pending);
        const kind = modal.dataset.kind;
        const body = { kind: kind, device_id: choice.getAttribute('data-device') };
        if (modal.dataset.password) body.password = modal.dataset.password;
        const name = choice.getAttribute('data-name') || 'the terminal';
        const url = modal.dataset.url;
        choice.classList.add('cred-busy');
        button.addClass('wait');
        if ((kind === 'face' || kind === 'finger') && err) {
            err.hidden = false;
            err.className = 'cred-machine-error is-wait';
            err.textContent = kind === 'face'
                ? 'Look at ' + name + ' now. Stay still until the camera finishes.'
                : 'Place one finger on ' + name + '. Wait until it finishes.';
        }
        $.ajax({
            url: url,
            method: 'POST',
            data: body,
            timeout: 75000,
            headers: { 'X-CSRF-TOKEN': csrf }
        }).done(function (data) {
            paintCounts(button.closest('.ac-cred'), data.counts || {}, data.slots || null);
            if (window.toast) window.toast(data.message || 'Sent to the terminal.');
            watchCredentials(button.closest('.ac-cred'));
            choice.classList.remove('is-offline');
            choice.classList.add('is-online');
            choice.setAttribute('data-online', '1');
            const state = choice.querySelector('.cred-machine-state');
            if (state) state.textContent = 'Online';
            pending.classList.remove('cred-pending');
            bootstrap.Modal.getInstance(modal)?.hide();
        }).fail(function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The terminal could not be updated.';
            if (window.toast) window.toast(message, 'er', 9000);
            if (/offline/i.test(message)) {
                choice.classList.remove('is-online');
                choice.classList.add('is-offline');
                choice.setAttribute('data-online', '0');
                const state = choice.querySelector('.cred-machine-state');
                if (state) state.textContent = 'Offline';
            } else {
                choice.classList.remove('is-offline');
                choice.classList.add('is-online');
                choice.setAttribute('data-online', '1');
                const state = choice.querySelector('.cred-machine-state');
                if (state) state.textContent = 'Online';
            }
            if (err) {
                err.className = 'cred-machine-error';
                err.textContent = message;
                err.hidden = false;
            }
        }).always(function () {
            choice.classList.remove('cred-busy');
            button.removeClass('wait');
        });
    });

    function removeCredential(button) {
        const pairs = (button.attr('data-slots') || '').split(',').filter(Boolean).map(function (pair) {
            const parts = pair.split(':');
            return { id: parts[0], slot: parts[1] || parts[0] };
        });
        if (!pairs.length) return;
        const kind = button.attr('data-kind');
        const run = function (target) {
            if (!target) return;
            button.addClass('wait');
            $.ajax({
                url: button.attr('data-remove') + '/' + target.id,
                method: 'DELETE',
                timeout: 60000,
                headers: { 'X-CSRF-TOKEN': csrf }
            }).done(function (data) {
                paintCounts(button.closest('.ac-cred'), data.counts || {}, data.slots || null);
                if (window.toast) window.toast(data.message || 'Removed from the terminal.');
            }).fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The terminal could not remove it.';
                if (window.toast) window.toast(message, 'er');
            }).always(function () {
                button.removeClass('wait');
            });
        };

        if (pairs.length > 1) {
            const answer = window.prompt('Remove which ' + kind + '? ' + pairs.map(function (pair) { return pair.slot; }).join(', '), pairs[pairs.length - 1].slot);
            if (!answer) return;
            run(pairs.find(function (pair) { return pair.slot === answer; }));
            return;
        }

        const label = kind === 'finger' ? @json(__('fingerprint')) : kind;
        window.askConfirm(
            @json(__('Remove this :item from the employee record and the face terminal? This cannot be undone.')).replace(':item', label),
            { label: @json(__('Remove')), danger: true }
        ).then(function (ok) {
            if (ok) run(pairs[0]);
        });
    }

    function paintCounts(box, counts, slots) {
        box.find('.ac-item').each(function () {
            const kind = this.getAttribute('data-kind');
            const total = counts[kind] || 0;
            const item = $(this);
            item.find('b').text(total);
            item.toggleClass('on', total > 0);
            if (slots && slots[kind]) {
                this.setAttribute('data-slots', slots[kind].map(function (row) { return row.id + ':' + row.slot; }).join(','));
            } else if (slots) {
                this.setAttribute('data-slots', '');
            }
            let mark = item.find('.ac-x');
            const removable = total > 0 && (kind === 'face' || kind === 'finger');
            if (removable && !mark.length) {
                mark = $('<span class="ac-x" title="Remove">×</span>');
                item.append(mark);
            }
            mark.toggle(removable);
        });
    }

    function watchCredentials(box) {
        const url = box.attr('data-state');
        if (!url) return;
        const previous = box.data('watchTimer');
        if (previous) window.clearInterval(previous);
        let tries = 0;
        const timer = window.setInterval(function () {
            tries += 1;
            $.getJSON(url).done(function (data) {
                paintCounts(box, data.counts || {}, data.slots || null);
            });
            if (tries >= 36) {
                window.clearInterval(timer);
                box.removeData('watchTimer');
            }
        }, 5000);
        box.data('watchTimer', timer);
    }

    bindRowActions();
})(jQuery);
</script>
@endpush
