<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'code', 'is_paid', 'annual_days',
        'counts_weekly_off', 'counts_holidays', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'annual_days' => 'decimal:1',
            'counts_weekly_off' => 'boolean',
            'counts_holidays' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
