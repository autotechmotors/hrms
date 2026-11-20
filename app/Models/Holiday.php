<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use AuditsChanges, BelongsToCompany;

    public const KINDS = [
        'public' => 'Public',
        'cutoff' => 'Cutoff',
        'restricted' => 'Restricted',
        'optional' => 'Optional',
    ];

    public const KIND_HELP = [
        'public' => 'Paid day for everyone. No punch needed.',
        'cutoff' => 'This day is cut from everyone’s salary. No punch needed.',
        'restricted' => 'Shown on the calendar. It does not pay or cut a day by itself.',
        'optional' => 'Shown on the calendar. It does not pay or cut a day by itself.',
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
            'cutoff' => 'er',
            'restricted' => 'wn',
            default => 'ac',
        };
    }

    public function closesTheCompany(): bool
    {
        return in_array($this->kind, ['public', 'cutoff'], true);
    }

    public function longDate(): string
    {
        return $this->holiday_date?->format('l, j F Y') ?: '—';
    }
}
