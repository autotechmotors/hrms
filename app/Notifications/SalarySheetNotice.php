<?php

namespace App\Notifications;

use App\Models\SalarySheet;
use Illuminate\Notifications\Notification;

class SalarySheetNotice extends Notification
{
    public function __construct(public SalarySheet $sheet, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $sheet = $this->sheet->loadMissing(['department', 'decider', 'author']);
        $sheet->loadCount('lines');
        $period = $sheet->starts_on->format('d M Y').' – '.$sheet->ends_on->format('d M Y');
        $people = $sheet->lines_count.' '.($sheet->lines_count === 1 ? 'person' : 'people');

        [$title, $body] = match ($this->event) {
            'approved' => ['Salary approved', $sheet->title.' for '.$period.' ('.$people.') was approved'.($sheet->decider ? ' by '.$sheet->decider->name : '').'.'],
            'rejected' => ['Salary rejected', $sheet->title.' for '.$period.' ('.$people.') was rejected'.($sheet->decider ? ' by '.$sheet->decider->name : '').'. Reason: '.$sheet->decision_note],
            default => ['Salary request', $sheet->title.' for '.$period.' ('.$people.') is waiting for the accountant.'.($sheet->author ? ' Sent by '.$sheet->author->name.'.' : '')],
        };

        return [
            'type' => 'salary_sheet_'.$this->event,
            'icon' => match ($this->event) {
                'approved' => 'ok',
                'rejected' => 'er',
                default => 'wn',
            },
            'salary_sheet_id' => $sheet->id,
            'title' => $title,
            'body' => $body,
            'status' => $sheet->status,
            'action_url' => route('salary-sheets.show', $sheet, false),
        ];
    }
}
