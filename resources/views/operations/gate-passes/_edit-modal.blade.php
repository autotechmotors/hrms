<div class="modal fade" id="gateEditModal" tabindex="-1" aria-labelledby="gateEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" id="gateEditForm" action="#" @if(!empty($live)) data-live="1" @endif>
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="gateEditTitle">{{ __('Edit Gate Pass') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body lv-modal-body">
                <p class="lv-modal-help" id="gateEditName"></p>
                <label class="form-label">{{ __('Work') }}</label>
                <select class="in w-100" name="work_type" required>
                    <option value="personal">{{ __('Personal Work') }}</option>
                    <option value="office">{{ __('Office Work') }}</option>
                </select>
                <label class="form-label">{{ __('Return') }}</label>
                <select class="in w-100" name="leave_type" id="gateEditLeave" required>
                    <option value="return">{{ __('Coming back') }}</option>
                    <option value="early_leave">{{ __('Early leave') }}</option>
                </select>
                <label class="form-label">{{ __('Out time') }}</label>
                <input class="in w-100" type="time" name="out_time" required>
                <div id="gateEditDuration">
                    <label class="form-label">{{ __('Duration') }}</label>
                    <div class="g c2">
                        <div>
                            <input class="in w-100" type="number" name="duration_hours" min="0" max="24" step="1" inputmode="numeric" required aria-label="{{ __('Hours') }}">
                            <small class="gp-hint">{{ __('Hours') }}</small>
                        </div>
                        <div>
                            <input class="in w-100" type="number" name="duration_minutes" min="0" max="59" step="1" inputmode="numeric" required aria-label="{{ __('Minutes') }}">
                            <small class="gp-hint">{{ __('Minutes') }}</small>
                        </div>
                    </div>
                    <small class="gp-hint">{{ __('How long they may stay out. Minimum 15 minutes.') }}</small>
                </div>
                <label class="form-label">{{ __('Reason') }}</label>
                <textarea class="in w-100" name="reason" required minlength="3" maxlength="1000" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn pr">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
    <script>
    (function ($) {
        const modal = document.getElementById('gateEditModal');
        const form = document.getElementById('gateEditForm');
        if (!modal || !form) return;

        function syncLeave() {
            const early = form.querySelector('[name="leave_type"]').value === 'early_leave';
            const wrap = document.getElementById('gateEditDuration');
            if (wrap) wrap.hidden = early;
            form.querySelectorAll('[name="duration_hours"], [name="duration_minutes"]').forEach(function (input) {
                input.required = !early;
            });
        }

        modal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn || !btn.hasAttribute('data-gp-edit')) return;
            form.action = btn.getAttribute('data-action') || '#';
            form.querySelector('[name="out_time"]').value = btn.getAttribute('data-out') || '';
            const duration = (btn.getAttribute('data-duration') || '01:00').split(':');
            form.querySelector('[name="duration_hours"]').value = String(parseInt(duration[0] || '0', 10));
            form.querySelector('[name="duration_minutes"]').value = String(parseInt(duration[1] || '0', 10));
            form.querySelector('[name="reason"]').value = btn.getAttribute('data-reason') || '';
            form.querySelector('[name="work_type"]').value = btn.getAttribute('data-work') || 'personal';
            form.querySelector('[name="leave_type"]').value = btn.getAttribute('data-leave') || 'return';
            const name = document.getElementById('gateEditName');
            if (name) name.textContent = [btn.getAttribute('data-number'), btn.getAttribute('data-name')].filter(Boolean).join(' · ');
            syncLeave();
        });

        form.querySelector('[name="leave_type"]').addEventListener('change', syncLeave);

        function refreshList() {
            const host = document.querySelector('[data-live-list="gate-passes"]');
            if (!host) {
                window.location.reload();
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.set('partial', '1');
            $.ajax({
                url: url.toString(),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const next = doc.querySelector('[data-live-list="gate-passes"]');
                if (next) host.replaceWith(document.importNode(next, true));
                const meta = doc.querySelector('.lv-meta');
                const metaNow = document.querySelector('.lv-meta');
                if (meta && metaNow) metaNow.textContent = meta.textContent;
                const head = doc.querySelector('.gp-page .ph p');
                const headNow = document.querySelector('.gp-page .ph p');
                if (head && headNow) headNow.textContent = head.textContent;
                doc.querySelectorAll('.lv-stats b').forEach(function (node, index) {
                    const live = document.querySelectorAll('.lv-stats b')[index];
                    if (live) live.textContent = node.textContent;
                });
                const today = doc.querySelector('.gp-today');
                const todayNow = document.querySelector('.gp-today');
                if (today && todayNow) todayNow.replaceWith(document.importNode(today, true));
                else if (!today && todayNow) todayNow.remove();
            });
        }

        function send(target) {
            $.ajax({
                url: target.action,
                method: 'POST',
                data: new FormData(target),
                processData: false,
                contentType: false,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).done(function (body) {
                if (window.toast) window.toast((body && body.message) || 'Saved');
                const open = bootstrap.Modal.getInstance(modal);
                if (open) open.hide();
                refreshList();
            }).fail(function (xhr) {
                const errors = xhr.responseJSON && xhr.responseJSON.errors;
                const first = errors ? Object.values(errors)[0] : null;
                if (window.toast) window.toast((first && first[0]) || (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save', 'er');
            });
        }

        document.addEventListener('submit', function (event) {
            const target = event.target;
            if (!target || !target.getAttribute || target.getAttribute('data-live') !== '1') return;
            if (target.id !== 'gateEditForm' && !target.classList.contains('gp-row-delete')) return;
            event.preventDefault();
            event.stopPropagation();
            if (target.dataset.confirm) {
                window.askConfirm(target.dataset.confirm, { danger: true, label: 'Delete' }).then(function (ok) {
                    if (ok) send(target);
                });
                return;
            }
            send(target);
        }, true);
    })(jQuery);
    </script>
    @endpush
@endonce
