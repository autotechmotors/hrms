<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CompanyBankAccount extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = [
        'company_id', 'label', 'account_holder', 'bank_name', 'branch_name',
        'account_number', 'account_last4', 'ifsc', 'gst_number', 'is_primary',
    ];

    protected $hidden = ['account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'is_primary' => 'boolean'];
    }

    public function getMaskedAccountAttribute(): string
    {
        return 'XXXXXX'.$this->account_last4;
    }
}
