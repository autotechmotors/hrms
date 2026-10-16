<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExit extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'employee_id',
        'resignation_date',
        'last_working_date',
        'reason',
        'employment_status_id',
        'fnf_status',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'resignation_date' => 'date',
            'last_working_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class);
    }
}
