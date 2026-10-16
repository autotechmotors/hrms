<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use AuditsChanges, BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'model', 'serial', 'ip_address', 'place', 'status', 'last_checked_at'];

    protected function casts(): array
    {
        return ['last_checked_at' => 'datetime'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }
}
