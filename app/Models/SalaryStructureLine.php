<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructureLine extends Model
{
    protected $fillable = ['salary_structure_id', 'salary_head_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(SalaryHead::class, 'salary_head_id');
    }
}
