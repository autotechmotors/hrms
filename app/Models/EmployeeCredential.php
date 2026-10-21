<?php

namespace App\Models;

use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCredential extends Model
{
    protected $fillable = ['employee_id', 'kind', 'slot', 'content'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function imageUri(): ?string
    {
        return StoredImage::uri($this->content);
    }
}
