<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'code', 'starts_at', 'ends_at', 'break_minutes', 'is_flexible', 'is_active'];

    protected function casts(): array
    {
        return ['is_flexible' => 'boolean', 'is_active' => 'boolean'];
    }
}
