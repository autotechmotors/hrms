<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use AuditsChanges, BelongsToCompany;

    public const STATUSES = [
        'draft' => 'Draft',
        'computed' => 'Calculated',
        'approved' => 'Approved',
    ];

    protected $fillable = ['company_id', 'period_year', 'period_month', 'status', 'working_days'];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function periodLabel(): string
    {
        return Carbon::create((int) $this->period_year, (int) $this->period_month, 1)->format('F Y');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'approved' => 'ok',
            'computed' => 'ac',
            default => 'wn',
        };
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isComputed(): bool
    {
        return $this->status === 'computed';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
