<script>
(function ($) {
    if (window.sgSalaryBound) return;
    window.sgSalaryBound = true;
    var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function fail(xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.reason
            ? xhr.responseJSON.errors.reason[0]
            : (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not save the decision.');
        window.toast(message, 'er');
    }

    function done(res) {
        window.toast(res.message);
        if (res.decision && document.getElementById('sgDecision')) {
            $('#sgDecision').replaceWith(res.decision);
        }
        if (window.sgReloadHistory) window.sgReloadHistory();
    }

    $(document).on('click', '.sg-approve', function () {
        var url = $(this).data('url');
        window.askConfirm(@json(__('Approve this salary request?')), { label: @json(__('Approve')) }).then(function (ok) {
            if (!ok) return;
            $.ajax({ url: url, method: 'POST', dataType: 'json', data: { _token: token } }).done(done).fail(fail);
        });
    });

    $(document).on('click', '.sg-reject', function () {
        $('#sgRejectForm').data('url', $(this).data('url'));
        $('#sgRejectForm textarea').val('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('sgRejectModal')).show();
    });

    $(document).on('submit', '#sgRejectForm', function (event) {
        event.preventDefault();
        var reason = $.trim($(this).find('textarea').val());
        if (reason.length < 3) {
            window.toast('A reason is required.', 'er');
            return;
        }
        $.ajax({
            url: $(this).data('url'),
            method: 'POST',
            dataType: 'json',
            data: { _token: token, reason: reason }
        }).done(function (res) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sgRejectModal')).hide();
            done(res);
        }).fail(fail);
    });

    function money(value) {
        return '₹' + Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    $(document).on('click', '.sg-net-edit', function () {
        var button = $(this);
        var form = $('#sgNetForm');
        form.data('url', button.data('url'));
        form.data('line', button.closest('[data-line]').attr('data-line'));
        $('#sgNetName').text(button.data('name') + ' · one person only. A reason is required.');
        $('#sgNetCounted').text(money(button.data('computed')));
        form.find('[name="net"]').val(button.data('net'));
        form.find('[name="reason"]').val('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('sgNetModal')).show();
    });

    $(document).on('submit', '#sgNetForm', function (event) {
        event.preventDefault();
        var form = $(this);
        var reason = $.trim(form.find('[name="reason"]').val());
        if (reason.length < 3) {
            window.toast('A reason is required to change net pay.', 'er');
            return;
        }
        $.ajax({
            url: form.data('url'),
            method: 'POST',
            dataType: 'json',
            data: {
                _token: token,
                net: form.find('[name="net"]').val(),
                reason: reason
            }
        }).done(function (res) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sgNetModal')).hide();
            window.toast(res.message || 'Net pay updated.');
            var card = document.querySelector('[data-line="' + res.line_id + '"]');
            if (card) {
                var net = card.querySelector('.js-net');
                var computed = card.querySelector('.js-computed');
                var note = card.querySelector('.js-net-note');
                var button = card.querySelector('.sg-net-edit');
                if (net) net.textContent = money(res.net);
                if (computed) computed.textContent = money(res.computed_net);
                if (button) {
                    button.setAttribute('data-net', Number(res.net).toFixed(2));
                    button.setAttribute('data-computed', Number(res.computed_net).toFixed(2));
                }
                if (note) {
                    var sign = Number(res.delta) >= 0 ? '+' : '';
                    note.hidden = false;
                    note.textContent = 'Net changed from ' + money(res.computed_net) + ' to ' + money(res.net)
                        + ' (' + sign + Number(res.delta).toFixed(2) + '). Reason: ' + res.reason;
                }
            }
            var sheetNet = document.querySelector('.sg-stats .kp:last-child b');
            if (sheetNet && res.sheet_net !== undefined) sheetNet.textContent = money(res.sheet_net);
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.errors
                ? (Object.values(xhr.responseJSON.errors)[0] || [])[0]
                : (xhr.responseJSON && xhr.responseJSON.message);
            window.toast(message || 'Could not change net pay.', 'er');
        });
    });
})(jQuery);
</script>
