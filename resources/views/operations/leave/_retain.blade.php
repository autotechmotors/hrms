@if(!empty($filters['q']))
    <input type="hidden" name="retain_q" value="{{ $filters['q'] }}">
@endif
@if(!empty($filters['status']))
    <input type="hidden" name="retain_status" value="{{ $filters['status'] }}">
@endif
@if(!empty($filters['leave_type_id']))
    <input type="hidden" name="retain_type" value="{{ $filters['leave_type_id'] }}">
@endif
