<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_id',
        'block_inactive_enabled',
        'block_inactive_days',
    ];

    protected function casts(): array
    {
        return [
            'block_inactive_enabled' => 'boolean',
            'block_inactive_days' => 'integer',
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
                'block_inactive_enabled' => false,
                'block_inactive_days' => 7,
            ]
        );
    }
}
