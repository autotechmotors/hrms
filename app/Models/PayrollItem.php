<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'paid_days', 'lop_days',
        'gross', 'deductions', 'net', 'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'paid_days' => 'decimal:1',
            'lop_days' => 'decimal:1',
            'gross' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return list<array{name: string, category: string, amount: float}>
     */
    public function earningLines(): array
    {
        return collect($this->breakdown ?? [])
            ->filter(fn (array $line): bool => ($line['category'] ?? '') !== 'deduction')
            ->map(fn (array $line): array => [
                'name' => (string) ($line['name'] ?? 'Earning'),
                'category' => 'earning',
                'amount' => (float) ($line['amount'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, category: string, amount: float}>
     */
    public function deductionLines(): array
    {
        $lines = collect($this->breakdown ?? [])
            ->filter(fn (array $line): bool => ($line['category'] ?? '') === 'deduction')
            ->map(fn (array $line): array => [
                'name' => (string) ($line['name'] ?? 'Deduction'),
                'category' => 'deduction',
                'amount' => (float) ($line['amount'] ?? 0),
            ])
            ->values();

        $named = (float) $lines->sum(fn (array $line): float => $line['amount']);
        $remainder = round((float) $this->deductions - $named, 2);
        if ($remainder > 0.009) {
            $lines->push([
                'name' => 'Advance EMI',
                'category' => 'deduction',
                'amount' => $remainder,
            ]);
        }

        return $lines->all();
    }

    public function daysSummary(int $workingDays): string
    {
        $paid = $this->qty((float) $this->paid_days);
        $unpaid = $this->qty((float) $this->lop_days);
        if ((float) $this->lop_days <= 0) {
            return "Paid {$paid} of {$workingDays} working days";
        }

        return "Paid {$paid} of {$workingDays} working days · {$unpaid} unpaid (absent or unpaid leave)";
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
