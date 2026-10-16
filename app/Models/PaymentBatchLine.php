<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentBatchLine extends Model
{
    protected $fillable = ['payment_batch_id', 'employee_id', 'amount', 'account_last4'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
