<?php

namespace App\Models;

use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    protected $fillable = ['employee_id', 'device_id', 'gate_pass_id', 'punched_at', 'direction', 'image', 'external_key'];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function gatePass(): BelongsTo
    {
        return $this->belongsTo(GatePass::class);
    }

    public function imageUri(): ?string
    {
        return StoredImage::uri($this->image);
    }
}
