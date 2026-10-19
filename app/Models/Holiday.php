<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use AuditsChanges, BelongsToCompany;

    public const KINDS = [
        'public' => 'Public holiday',
        'restricted' => 'Restricted holiday',
        'optional' => 'Optional holiday',
    ];

    public const KIND_HELP = [
        'public' => 'Office closed. Non-working day for everyone.',
        'restricted' => 'Shown on the calendar. Leave types decide if it is counted.',
        'optional' => 'Optional for employees. Follows leave settings.',
    ];

    protected $fillable = ['company_id', 'holiday_date', 'name', 'kind'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function kindHelp(): string
    {
        return self::KIND_HELP[$this->kind] ?? '';
    }

    public function kindBadgeClass(): string
    {
        return match ($this->kind) {
            'public' => 'ok',
            'restricted' => 'wn',
            default => 'ac',
        };
    }

    public function longDate(): string
    {
        return $this->holiday_date?->format('l, j F Y') ?: '—';
    }
}
