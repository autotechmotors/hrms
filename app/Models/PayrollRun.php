<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'period_year', 'period_month', 'status', 'working_days'];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
