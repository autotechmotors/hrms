<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTerminalUser extends Model
{
    protected $fillable = ['device_id', 'user_id', 'name', 'employee_id', 'dismissed_at', 'synced_at'];

    protected function casts(): array
    {
        return ['dismissed_at' => 'datetime', 'synced_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
