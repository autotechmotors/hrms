<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class EmploymentStatus extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'code', 'description', 'is_exit', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_exit' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
