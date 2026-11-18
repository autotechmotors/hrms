<?php

namespace App\Services\Operations;

use App\Models\Advance;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SalaryStructureLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollRunner
{
    public function __construct(private readonly PayDayMath $pay) {}

    public function compute(PayrollRun $run): PayrollRun
    {
        if ($run->status === 'approved') {
            throw ValidationException::withMessages(['payroll' => 'An approved payroll cannot be recalculated.']);
        }

        return DB::transaction(function () use ($run): PayrollRun {
            $start = Carbon::create($run->period_year, $run->period_month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $run->update(['working_days' => $start->daysInMonth, 'status' => 'computed']);
            $run->items()->delete();

            Employee::query()
                ->where('company_id', $run->company_id)
                ->whereHas('employmentStatus', fn ($query) => $query->where('is_exit', false))
                ->orderBy('id')
                ->each(function (Employee $employee) use ($run, $start, $end): void {
                    $salary = EmployeeSalary::query()
                        ->where('employee_id', $employee->id)
                        ->whereDate('effective_from', '<=', $end)
                        ->orderByDesc('effective_from')
                        ->with('structure.lines.head')
                        ->first();

                    if (! $salary || ! $salary->structure) {
                        return;
                    }

                    $pay = $this->pay->range($employee, $start, $end);
                    $payableGross = $pay['earned'];
                    [$earnings, $deductions, $lines] = $this->lines($salary->structure->lines, $payableGross);
                    foreach ($pay['cuts'] as $cut) {
                        $lines[] = [
                            'name' => $cut['date'].' · '.$cut['window'].' · '.$cut['reason'],
                            'category' => 'cutoff',
                            'amount' => $cut['rupees'],
                            'date' => $cut['date'],
                            'window' => $cut['window'],
                            'in' => $cut['in'],
                            'out' => $cut['out'],
                            'minutes' => $cut['minutes'],
                            'reason' => $cut['reason'],
                        ];
                    }
                    if ($pay['cutoff'] > 0) {
                        $lines[] = ['name' => 'Salary cutoff', 'category' => 'cutoff', 'amount' => $pay['cutoff']];
                    }
                    $paid = $pay['paid_fraction'];
                    $lop = $pay['lop_fraction'];
                    $advanceTotal = 0.0;
                    foreach ($this->monthAdvances((int) $employee->id, $start) as $advance) {
                        $take = $this->advanceTake($advance);
                        if ($take <= 0) {
                            continue;
                        }
                        $lines[] = ['name' => 'Advance · '.$advance->title, 'category' => 'deduction', 'amount' => $take];
                        $advanceTotal += $take;
                    }
                    $deductions = round($deductions + $advanceTotal, 2);

                    PayrollItem::query()->create([
                        'payroll_run_id' => $run->id,
                        'employee_id' => $employee->id,
                        'paid_days' => $paid,
                        'lop_days' => $lop,
                        'gross' => $earnings,
                        'deductions' => $deductions,
                        'net' => max(0, round($earnings - $deductions, 2)),
                        'breakdown' => $lines,
                    ]);
                });

            return $run->refresh();
        });
    }

    public function approve(PayrollRun $run): void
    {
        if ($run->status !== 'computed') {
            throw ValidationException::withMessages(['payroll' => 'Compute the payroll before approving it.']);
        }

        DB::transaction(function () use ($run): void {
            $start = Carbon::create($run->period_year, $run->period_month, 1);
            foreach ($run->items as $item) {
                foreach ($this->monthAdvances((int) $item->employee_id, $start) as $advance) {
                    if ($this->advanceTake($advance) <= 0) {
                        continue;
                    }
                    $advance->balance = 0;
                    $advance->status = 'completed';
                    $advance->save();
                }
            }
            $run->update(['status' => 'approved']);
        });
    }

    /**
     * @param  iterable<int, SalaryStructureLine>  $lines
     * @return array{0: float, 1: float, 2: list<array{name: string, category: string, amount: float}>}
     */
    private function lines(iterable $lines, float $gross): array
    {
        $earningLines = [];
        foreach ($lines as $line) {
            $head = $line->head;
            if ($head && $head->is_active && $head->category === 'earning') {
                $earningLines[] = $line;
            }
        }
        usort($earningLines, function (SalaryStructureLine $left, SalaryStructureLine $right): int {
            $leftBasic = strtoupper((string) $left->head?->code) === 'BASIC' ? 0 : 1;
            $rightBasic = strtoupper((string) $right->head?->code) === 'BASIC' ? 0 : 1;

            return $leftBasic <=> $rightBasic;
        });

        $earned = [];
        $basic = 0.0;
        $rows = [];
        foreach ($earningLines as $line) {
            $head = $line->head;
            $amount = $this->amount($head->method, $line->value !== null ? (float) $line->value : (float) $head->value, $gross, $basic);
            if (strtoupper($head->code) === 'BASIC') {
                $basic = $amount;
            }
            $earned[] = $amount;
            $rows[] = ['name' => $head->name, 'category' => 'earning', 'amount' => $amount];
        }

        $deducted = 0.0;
        foreach ($lines as $line) {
            $head = $line->head;
            if (! $head || ! $head->is_active || $head->category !== 'deduction') {
                continue;
            }
            $amount = $this->amount($head->method, $line->value !== null ? (float) $line->value : (float) $head->value, $gross, $basic);
            $deducted += $amount;
            $rows[] = ['name' => $head->name, 'category' => 'deduction', 'amount' => $amount];
        }

        return [round(array_sum($earned), 2), round($deducted, 2), $rows];
    }

    private function amount(string $method, float $value, float $gross, float $basic): float
    {
        return match ($method) {
            'percent_gross' => round($gross * $value / 100, 2),
            'percent_basic' => round($basic * $value / 100, 2),
            default => round($value, 2),
        };
    }

    /**
     * Advances given during this payroll month. The full open amount is deducted once, from that month only.
     *
     * @return Collection<int, Advance>
     */
    private function monthAdvances(int $employeeId, Carbon $start)
    {
        return Advance::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->whereDate('starts_on', '>=', $start->toDateString())
            ->whereDate('starts_on', '<=', $start->copy()->endOfMonth()->toDateString())
            ->orderBy('id')
            ->get();
    }

    private function advanceTake(Advance $advance): float
    {
        return max(0, min((float) $advance->amount, (float) $advance->balance));
    }
}
