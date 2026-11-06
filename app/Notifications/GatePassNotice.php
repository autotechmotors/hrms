<?php

namespace App\Notifications;

use App\Models\GatePass;
use Illuminate\Notifications\Notification;

class GatePassNotice extends Notification
{
    public function __construct(public GatePass $gatePass, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $pass = $this->gatePass->loadMissing(['employee.department', 'employee.manager', 'decider']);
        $employee = $pass->employee;
        $name = $employee?->full_name ?? 'An employee';
        $when = $pass->formatted_date.' at '.$pass->formatted_out_time;
        $type = $pass->work_type_label;

        [$title, $body] = match ($this->event) {
            'approved' => ['Gate Pass Approved', $name.' · '.$type.' for '.$when.' has been approved.'.($pass->decider ? ' By '.$pass->decider->name.'.' : '').' OUT machine is available until '.$pass->expires_at?->format('h:i A').'.'],
            'rejected' => ['Gate Pass Rejected', trim($name.' · '.$type.' for '.$pass->formatted_date.' has been rejected. OUT machine was not opened.'.($pass->decision_note ? ' Reason: '.$pass->decision_note : ''))],
            'expired' => ['Gate Pass Expired', $name.' · '.$type.' for '.$when.' has expired. OUT machine access is removed.'],
            'still_out' => ['Still out after Gate Pass', $name.' has not returned. Clock-in will start from the next punch. If there is no return today, this counts as half-day early leave.'],
            default => ['New Gate Pass Request', $name.' requested a '.$type.' Gate Pass for '.$when.'. Parent must approve before the OUT machine will allow exit.'],
        };

        return [
            'type' => 'gate_pass_'.$this->event,
            'icon' => match ($this->event) {
                'approved' => 'ok',
                'rejected' => 'er',
                default => 'wn',
            },
            'gate_pass_id' => $pass->id,
            'number' => $pass->number,
            'title' => $title,
            'body' => $body,
            'employee' => $name,
            'employee_code' => $employee?->employee_code,
            'department' => $employee?->department?->name,
            'parent' => $employee?->manager?->full_name,
            'work_type' => $type,
            'when' => $when,
            'reason' => $pass->reason,
            'status' => $pass->status_label,
            'action_url' => route('gate-passes.show', $pass, false),
        ];
    }
}
