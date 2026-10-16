<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advance extends Model
{
    use AuditsChanges;

    protected $fillable = ['employee_id', 'title', 'amount', 'emi', 'balance', 'starts_on', 'status'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'emi' => 'decimal:2',
            'balance' => 'decimal:2',
            'starts_on' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
