<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentWorkHour extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'department_id',
        'hours_per_day',
        'weekday_start',
        'weekday_end',
        'sunday_start',
        'sunday_end',
        'night_weekday_start',
        'night_weekday_end',
        'night_sunday_start',
        'night_sunday_end',
        'night_works_sunday',
        'weekday_break_start',
        'weekday_break_end',
        'sunday_break_start',
        'sunday_break_end',
        'night_weekday_break_start',
        'night_weekday_break_end',
        'night_sunday_break_start',
        'night_sunday_break_end',
    ];

    protected function casts(): array
    {
        return [
            'hours_per_day' => 'decimal:2',
            'night_works_sunday' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
