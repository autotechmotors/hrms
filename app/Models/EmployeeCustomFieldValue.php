<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCustomFieldValue extends Model
{
    protected $fillable = ['employee_id', 'employee_custom_field_id', 'value'];

    public function field(): BelongsTo
    {
        return $this->belongsTo(EmployeeCustomField::class, 'employee_custom_field_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
