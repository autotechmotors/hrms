<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDay extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'shift_id', 'punch_in', 'punch_out',
        'status', 'minutes_worked', 'source', 'remarks',
    ];

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
