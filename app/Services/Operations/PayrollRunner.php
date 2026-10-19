<?php

namespace App\Services\Operations;

use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\SalaryStructureLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollRunner
{
    public function __construct(private readonly WorkCalendarResolver $calendar) {}

    public function compute(PayrollRun $run): PayrollRun
    {
        if ($run->status === 'approved') {
            throw ValidationException::withMessages(['payroll' => 'An approved payroll cannot be recalculated.']);
        }

        return DB::transaction(function () use ($run): PayrollRun {
            $workingDays = $this->calendar->workingDaysInMonth((int) $run->company_id, (int) $run->period_year, (int) $run->period_month);
            $run->update(['working_days' => $workingDays, 'status' => 'computed']);
            $run->items()->delete();

            $start = Carbon::create($run->period_year, $run->period_month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();

            Employee::query()
                ->where('company_id', $run->company_id)
                ->whereHas('employmentStatus', fn ($query) => $query->where('is_exit', false))
                ->orderBy('id')
                ->each(function (Employee $employee) use ($run, $start, $end, $workingDays): void {
                    $salary = EmployeeSalary::query()
                        ->where('employee_id', $employee->id)
                        ->whereDate('effective_from', '<=', $end)
                        ->orderByDesc('effective_from')
                        ->with('structure.lines.head')
                        ->first();

                    if (! $salary || $workingDays === 0) {
                        return;
                    }

                    $lop = $this->lopDays($employee, $start, $end);
                    $paid = max(0, $workingDays - $lop);
                    $payableGross = round(((float) $salary->gross_monthly) * ($paid / $workingDays), 2);
                    [$earnings, $deductions, $lines] = $this->lines($salary->structure->lines, $payableGross);
                    $advance = $this->plannedAdvance((int) $employee->id, $start);
                    if ($advance > 0) {
                        $lines[] = ['name' => 'Advance EMI', 'category' => 'deduction', 'amount' => $advance];
                    }
                    $deductions = round($deductions + $advance, 2);

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
                $advance = Advance::query()
                    ->where('employee_id', $item->employee_id)
                    ->where('status', 'active')
                    ->whereDate('starts_on', '<=', $start->copy()->endOfMonth())
                    ->orderBy('id')
                    ->first();
                if (! $advance || (float) $advance->emi <= 0) {
                    continue;
                }
                $take = min((float) $advance->emi, (float) $advance->balance);
                $advance->balance = round((float) $advance->balance - $take, 2);
                if ((float) $advance->balance <= 0) {
                    $advance->status = 'completed';
                    $advance->balance = 0;
                }
                $advance->save();
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

    private function lopDays(Employee $employee, Carbon $start, Carbon $end): float
    {
        $rule = AttendanceRule::query()->firstOrCreate(
            ['company_id' => $employee->company_id],
            ['grace_minutes' => 0, 'mark_absent_without_punch' => true],
        );
        $days = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceDay $day) => $day->work_date->toDateString());
        $unpaid = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->whereHas('leaveType', fn ($query) => $query->where('is_paid', false))
            ->get();

        $lop = 0.0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($this->calendar->isWorkingDay((int) $employee->company_id, $cursor)) {
                $row = $days->get($cursor->toDateString());
                $onUnpaid = $unpaid->contains(fn (LeaveRequest $leave) => $cursor->between($leave->starts_on->copy()->startOfDay(), $leave->ends_on->copy()->endOfDay()));
                if ($row?->status === 'half_day') {
                    $lop += 0.5;
                } elseif ($row?->status === 'absent' || $onUnpaid || (! $row && $rule->mark_absent_without_punch)) {
                    $lop += 1;
                }
            }
            $cursor->addDay();
        }

        return $lop;
    }

    private function plannedAdvance(int $employeeId, Carbon $start): float
    {
        $advance = Advance::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $start->copy()->endOfMonth())
            ->orderBy('id')
            ->first();

        if (! $advance) {
            return 0;
        }

        return min((float) $advance->emi, (float) $advance->balance);
    }
}
