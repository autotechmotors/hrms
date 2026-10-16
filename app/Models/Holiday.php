<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'holiday_date', 'name', 'kind'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }
}
