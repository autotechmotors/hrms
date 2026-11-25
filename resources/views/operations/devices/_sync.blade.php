@once
    @push('scripts')
        <script>
            document.querySelectorAll('form[data-sync]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (form.dataset.confirmed !== '1' || form.dataset.syncing === '1') {
                        return;
                    }
                    event.preventDefault();
                    const button = form.querySelector('button');
                    const label = button ? button.textContent : '';
                    const totals = { added: 0, faces: 0, fingers: 0, copied: 0, failed: 0 };
                    let listed = false;
                    form.dataset.syncing = '1';
                    if (button) button.disabled = true;

                    const finish = function (message, kind) {
                        if (window.toast) window.toast(message, kind || '', kind === 'er' ? 9000 : 4000);
                        form.dataset.syncing = '';
                        form.dataset.confirmed = '';
                        if (button) {
                            button.disabled = false;
                            button.textContent = label;
                        }
                    };

                    const step = function (offset) {
                        window.jQuery.ajax({
                            url: form.action,
                            method: 'POST',
                            data: { _token: document.querySelector('meta[name="csrf-token"]').content, offset: offset },
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            timeout: 70000
                        }).done(function (data) {
                            totals.added += data.added || 0;
                            totals.faces += data.faces || 0;
                            totals.fingers += data.fingers || 0;
                            totals.copied += data.copied || 0;
                            totals.failed += data.failed || 0;
                            if (button) button.textContent = (data.offset || 0) + ' / ' + (data.total || 0);
                            if (!data.done) {
                                if (data.phase === 'listed') {
                                    if (listed) {
                                        finish('Sync stopped. Press Sync people again to continue.', 'er');
                                        return;
                                    }
                                    listed = true;
                                    step(0);
                                    return;
                                }
                                step(data.offset || 0);
                                return;
                            }
                            let message = 'Employees added: ' + totals.added + '. Faces saved: ' + totals.faces + '. Fingers saved: ' + totals.fingers + '. Copied to other terminals: ' + totals.copied + '.';
                            if (totals.failed > 0) message += ' ' + totals.failed + ' copies did not reach a terminal.';
                            finish(message);
                        }).fail(function (xhr) {
                            const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Sync stopped. Press Sync people again to continue.';
                            finish(message, 'er');
                        });
                    };

                    if (window.toast) window.toast('Reading the terminal. Stay on this page.');
                    step(0);
                });
            });
        </script>
    @endpush
@endonce
