<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AttendanceRule extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'grace_minutes', 'half_day_minutes', 'full_day_minutes', 'mark_absent_without_punch'];

    protected function casts(): array
    {
        return ['mark_absent_without_punch' => 'boolean'];
    }
}
