<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRoster extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'shift_id', 'is_off'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'is_off' => 'boolean'];
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
