<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCalendarDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['work_calendar_id', 'weekday', 'is_working'];

    protected function casts(): array
    {
        return ['is_working' => 'boolean'];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(WorkCalendar::class, 'work_calendar_id');
    }
}
