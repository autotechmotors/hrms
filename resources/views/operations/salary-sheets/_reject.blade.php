<div class="modal fade" id="sgRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="sgRejectForm">
            <div class="modal-header">
                <h5 class="modal-title">Reject salary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="sg-reject-reason">Reason
                    <textarea class="in" name="reason" required minlength="3" maxlength="500" placeholder="Why is this salary rejected?"></textarea>
                </label>
                <p class="sg-reject-hint">A reason is required. The person who generated the salary will see it.</p>
            </div>
            <div class="modal-footer">
                <button class="btn" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn pr" type="submit">Reject salary</button>
            </div>
        </form>
    </div>
</div>
