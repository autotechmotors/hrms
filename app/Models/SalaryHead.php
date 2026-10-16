<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SalaryHead extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'code', 'category', 'method', 'value', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
