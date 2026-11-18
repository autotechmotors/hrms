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
        window.askConfirm('Approve this salary request?', { label: 'Approve' }).then(function (ok) {
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
})(jQuery);
</script>
