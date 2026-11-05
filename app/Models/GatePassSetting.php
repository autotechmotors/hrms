<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatePassSetting extends Model
{
    protected $fillable = [
        'company_id',
        'allow_self_apply',
        'require_parent_approval',
        'prevent_duplicate_active',
        'max_duration_minutes',
        'default_duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'allow_self_apply' => 'boolean',
            'require_parent_approval' => 'boolean',
            'prevent_duplicate_active' => 'boolean',
            'max_duration_minutes' => 'integer',
            'default_duration_minutes' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function forCompany(int $companyId): self
    {
        return static::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'allow_self_apply' => true,
                'require_parent_approval' => true,
                'prevent_duplicate_active' => true,
                'max_duration_minutes' => 360,
                'default_duration_minutes' => 60,
            ]
        );
    }
}
