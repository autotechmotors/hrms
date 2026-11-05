<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentWorkHour extends Model
{
    use AuditsChanges;

    protected $fillable = ['department_id', 'hours_per_day'];

    protected function casts(): array
    {
        return ['hours_per_day' => 'decimal:2'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
