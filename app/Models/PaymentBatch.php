<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentBatch extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'payroll_run_id', 'company_bank_account_id', 'scheduled_on', 'status', 'total'];

    protected function casts(): array
    {
        return ['scheduled_on' => 'date', 'total' => 'decimal:2'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentBatchLine::class);
    }
}
