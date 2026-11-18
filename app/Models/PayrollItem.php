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
            ->filter(fn (array $line): bool => ($line['category'] ?? '') === 'earning')
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
                'name' => 'Advance',
                'category' => 'deduction',
                'amount' => $remainder,
            ]);
        }

        return $lines->all();
    }

    /**
     * @return list<array{name: string, amount: float, date: string, window: string, in: ?string, out: ?string, minutes: int, reason: string}>
     */
    public function cutoffLines(): array
    {
        return collect($this->breakdown ?? [])
            ->filter(fn (array $line): bool => ($line['category'] ?? '') === 'cutoff' && isset($line['date']))
            ->map(fn (array $line): array => [
                'name' => (string) ($line['name'] ?? 'Cutoff'),
                'amount' => (float) ($line['amount'] ?? 0),
                'date' => (string) ($line['date'] ?? ''),
                'window' => (string) ($line['window'] ?? ''),
                'in' => $line['in'] ?? null,
                'out' => $line['out'] ?? null,
                'minutes' => (int) ($line['minutes'] ?? 0),
                'reason' => (string) ($line['reason'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function cutoffTotal(): float
    {
        $named = collect($this->breakdown ?? [])
            ->first(fn (array $line): bool => ($line['category'] ?? '') === 'cutoff' && ($line['name'] ?? '') === 'Salary cutoff');

        if ($named) {
            return (float) $named['amount'];
        }

        return round(array_sum(array_column($this->cutoffLines(), 'amount')), 2);
    }

    public function daysSummary(int $workingDays): string
    {
        $paid = $this->qty((float) $this->paid_days);
        $unpaid = $this->qty((float) $this->lop_days);
        if ((float) $this->lop_days <= 0) {
            return "Paid {$paid} of {$workingDays} calendar days";
        }

        return "Paid {$paid} of {$workingDays} calendar days · {$unpaid} unpaid";
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
