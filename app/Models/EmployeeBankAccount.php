<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBankAccount extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'employee_id',
        'beneficiary_name',
        'bank_name',
        'account_number',
        'account_last4',
        'ifsc',
        'branch_name',
        'effective_from',
        'is_active',
        'created_by',
    ];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getMaskedAccountAttribute(): string
    {
        return 'XXXXXX'.$this->account_last4;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
