<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentEvent extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'employee_id',
        'event_type',
        'effective_date',
        'reason',
        'from_branch_id',
        'to_branch_id',
        'from_department_id',
        'to_department_id',
        'from_designation_id',
        'to_designation_id',
        'from_manager_id',
        'to_manager_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
