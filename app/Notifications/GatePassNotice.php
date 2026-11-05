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
        $employee = $this->gatePass->employee?->full_name ?? 'An employee';
        $when = $this->gatePass->formatted_date.' at '.$this->gatePass->formatted_out_time;
        $type = $this->gatePass->work_type_label;

        [$title, $body] = match ($this->event) {
            'approved' => ['Gate Pass Approved', 'Your '.$type.' Gate Pass for '.$when.' has been approved.'],
            'rejected' => ['Gate Pass Rejected', trim('Your '.$type.' Gate Pass request for '.$this->gatePass->formatted_date.' has been rejected.'.($this->gatePass->decision_note ? ' Reason: '.$this->gatePass->decision_note : ''))],
            default => ['New Gate Pass Request', $employee.' requested a '.$type.' Gate Pass for '.$when.'.'],
        };

        return [
            'type' => 'gate_pass_'.$this->event,
            'gate_pass_id' => $this->gatePass->id,
            'title' => $title,
            'body' => $body,
            'action_url' => route('gate-passes.show', $this->gatePass, false),
        ];
    }
}
