<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkHour extends Model
{
    use AuditsChanges;

    protected $fillable = ['employee_id', 'uses_department', 'hours_per_day'];

    protected function casts(): array
    {
        return [
            'uses_department' => 'boolean',
            'hours_per_day' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
