<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'paid_days', 'lop_days',
        'gross', 'deductions', 'net', 'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'paid_days' => 'decimal:1',
            'lop_days' => 'decimal:1',
            'gross' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
