<?php

namespace App\Notifications;

use App\Models\Advance;
use Illuminate\Notifications\Notification;

class AdvanceNotice extends Notification
{
    public function __construct(public Advance $advance, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $advance = $this->advance->loadMissing(['employee', 'author', 'decider']);
        $who = $advance->employee?->full_name ?? 'an employee';
        $amount = '₹'.number_format((float) $advance->amount, 2);

        [$title, $body] = match ($this->event) {
            'approved' => [
                'Advance approved',
                $amount.' · '.$advance->title.' for '.$who.' was approved'
                    .($advance->decider ? ' by '.$advance->decider->name : '').'.',
            ],
            'rejected' => [
                'Advance rejected',
                $amount.' · '.$advance->title.' for '.$who.' was rejected'
                    .($advance->decider ? ' by '.$advance->decider->name : '')
                    .'. Reason: '.$advance->decision_note,
            ],
            default => [
                'Advance request',
                $amount.' · '.$advance->title.' for '.$who.' is waiting for the accountant.'
                    .($advance->author ? ' Sent by '.$advance->author->name.'.' : ''),
            ],
        };

        return [
            'type' => 'advance_'.$this->event,
            'icon' => match ($this->event) {
                'approved' => 'ok',
                'rejected' => 'er',
                default => 'wn',
            },
            'advance_id' => $advance->id,
            'title' => $title,
            'body' => $body,
            'status' => $advance->status,
            'action_url' => route('advances.index', [], false),
        ];
    }
}
