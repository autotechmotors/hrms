<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class EmployeeCustomField extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'field_type',
        'options',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
