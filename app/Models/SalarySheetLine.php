<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalarySheetLine extends Model
{
    protected $fillable = [
        'salary_sheet_id', 'employee_id', 'monthly_salary', 'hours_per_day', 'hourly_rate',
        'attended_minutes', 'paid_minutes', 'ot_minutes', 'late_minutes', 'early_minutes', 'break_minutes',
        'absence_days', 'paid_leave_days', 'unpaid_leave_days',
        'earned', 'advance', 'deductions', 'net', 'days', 'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'hours_per_day' => 'decimal:2',
            'hourly_rate' => 'decimal:4',
            'absence_days' => 'decimal:1',
            'paid_leave_days' => 'decimal:1',
            'unpaid_leave_days' => 'decimal:1',
            'earned' => 'decimal:2',
            'advance' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net' => 'decimal:2',
            'days' => 'array',
            'breakdown' => 'array',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(SalarySheet::class, 'salary_sheet_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
