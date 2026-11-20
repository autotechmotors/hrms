<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;

class LeaveNotice extends Notification
{
    public function __construct(public LeaveRequest $leave, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $leave = $this->leave->loadMissing(['employee.department', 'leaveType', 'decider']);
        $name = $leave->employee?->full_name ?: 'An employee';
        $type = $leave->leaveType?->name ?: 'Leave';
        $dates = $leave->starts_on?->format('d M Y').' – '.$leave->ends_on?->format('d M Y');
        $cutoff = $leave->leaveType && ! $leave->leaveType->is_paid ? ' Salary cutoff: yes.' : ' Salary cutoff: no.';

        [$title, $body] = match ($this->event) {
            'approved' => ['Leave approved', $name.' · '.$type.' for '.$dates.' was approved.'.($leave->decider ? ' By '.$leave->decider->name.'.' : '').$cutoff],
            'rejected' => ['Leave rejected', $name.' · '.$type.' for '.$dates.' was rejected.'.($leave->decision_note ? ' Reason: '.$leave->decision_note : '')],
            default => ['Leave request', $name.' asked for '.$type.' on '.$dates.'.'.$cutoff.' Approve it, or reject it with a reason.'],
        };

        return [
            'type' => 'leave_'.$this->event,
            'icon' => match ($this->event) {
                'approved' => 'ok',
                'rejected' => 'er',
                default => 'wn',
            },
            'leave_request_id' => $leave->id,
            'title' => $title,
            'body' => $body,
            'status' => $leave->status,
            'action_url' => route('leave.index', ['status' => $leave->status], false),
        ];
    }
}
