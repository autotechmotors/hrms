<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScope extends Model
{
    use AuditsChanges;

    protected $fillable = ['user_id', 'scope_type', 'scope_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
