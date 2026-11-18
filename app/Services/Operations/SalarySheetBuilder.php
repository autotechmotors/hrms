<?php

namespace App\Services\Operations;

use App\Models\Advance;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalarySheet;
use App\Models\SalarySheetLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalarySheetBuilder
{
    public function __construct(private readonly PayDayMath $pay) {}

    /**
     * Freeze one sheet for the chosen people and dates.
     *
     * @param  list<int>  $employeeIds
     */
    public function make(int $companyId, ?int $departmentId, array $employeeIds, string $start, string $end, ?int $userId, ?string $title = null): SalarySheet
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $employeeIds)
            ->with(['department', 'designation'])
            ->orderBy('employee_code')
            ->get();

        return DB::transaction(function () use ($companyId, $departmentId, $employees, $from, $to, $userId, $title) {
            $sheet = SalarySheet::query()->create([
                'company_id' => $companyId,
                'department_id' => $departmentId,
                'created_by' => $userId,
                'title' => $title ?: 'Salary sheet',
                'starts_on' => $from->toDateString(),
                'ends_on' => $to->toDateString(),
                'status' => 'pending',
            ]);

            foreach ($employees as $employee) {
                SalarySheetLine::query()->create($this->line($sheet->id, $employee, $from, $to));
            }

            return $sheet->load('lines');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $sheetId, Employee $employee, Carbon $from, Carbon $to): array
    {
        $pay = $this->pay->range($employee, $from, $to);
        $hoursPerDay = $pay['hours_per_day'];
        $monthly = $pay['monthly'];
        $earned = $pay['earned'];
        $days = $pay['days'];
        $attended = $pay['attended_minutes'];
        $paid = 0;
        $late = $pay['late_minutes'];
        $early = $pay['early_minutes'];
        $break = $pay['break_minutes'];
        $absence = $pay['absence_days'];
        $paidLeave = $pay['paid_leave_days'];
        $unpaidLeave = $pay['unpaid_leave_days'];
        $rate = $pay['day_pay'];
        $basic = $this->basic($employee, $earned);
        $deductionLines = $this->deductionLines($employee, $earned, $basic);
        $deductions = round(array_sum(array_column($deductionLines, 'amount')), 2);
        $advances = $this->advances($employee, $from, $to);
        $advanceTotal = round(array_sum(array_column($advances, 'amount')), 2);
        $net = max(0, round($earned - $deductions - $advanceTotal, 2));

        return [
            'salary_sheet_id' => $sheetId,
            'employee_id' => $employee->id,
            'monthly_salary' => $monthly,
            'hours_per_day' => $hoursPerDay,
            'hourly_rate' => round($rate, 4),
            'attended_minutes' => $attended,
            'paid_minutes' => $paid,
            'ot_minutes' => 0,
            'late_minutes' => $late,
            'early_minutes' => $early,
            'break_minutes' => $break,
            'absence_days' => $absence,
            'paid_leave_days' => $paidLeave,
            'unpaid_leave_days' => $unpaidLeave,
            'earned' => $earned,
            'advance' => $advanceTotal,
            'deductions' => $deductions,
            'net' => $net,
            'days' => $days,
            'breakdown' => [
                'deductions' => $deductionLines,
                'advances' => $advances,
                'cuts' => $pay['cuts'],
                'cutoff' => $pay['cutoff'],
            ],
        ];
    }

    private function basic(Employee $employee, float $earned): float
    {
        $salary = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->with('structure.lines.head')
            ->first();

        $basic = $earned;
        foreach ($salary?->structure?->lines ?? [] as $line) {
            $head = $line->head;
            if (! $head || ! $head->is_active || $head->category !== 'earning') {
                continue;
            }
            if (! in_array($head->code, ['BASIC', 'SGBASIC'], true)) {
                continue;
            }
            $value = $line->value !== null ? (float) $line->value : (float) $head->value;
            $basic = $head->method === 'percent_gross' ? round($earned * $value / 100, 2) : round($value, 2);
        }

        return $basic;
    }

    /**
     * @return list<array{name: string, amount: float}>
     */
    private function deductionLines(Employee $employee, float $earned, float $basic): array
    {
        $salary = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->with('structure.lines.head')
            ->first();

        $rows = [];
        foreach ($salary?->structure?->lines ?? [] as $line) {
            $head = $line->head;
            if (! $head || ! $head->is_active || $head->category !== 'deduction') {
                continue;
            }
            $value = $line->value !== null ? (float) $line->value : (float) $head->value;
            $amount = match ($head->method) {
                'percent_gross' => round($earned * $value / 100, 2),
                'percent_basic' => round($basic * $value / 100, 2),
                default => $this->proratedFixed($value, $earned, (float) $salary->gross_monthly),
            };
            if ($amount <= 0) {
                continue;
            }
            $rows[] = ['name' => $head->name, 'amount' => $amount];
        }

        return $rows;
    }

    private function proratedFixed(float $monthlyFixed, float $earned, float $monthly): float
    {
        if ($monthlyFixed <= 0 || $monthly <= 0 || $earned <= 0) {
            return 0.0;
        }

        return round($monthlyFixed * ($earned / $monthly), 2);
    }

    /**
     * @return list<array{name: string, amount: float}>
     */
    private function advances(Employee $employee, Carbon $from, Carbon $to): array
    {
        return Advance::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->whereDate('starts_on', '>=', $from)
            ->whereDate('starts_on', '<=', $to)
            ->orderBy('id')
            ->get()
            ->map(function (Advance $advance) {
                $take = max(0, min((float) $advance->amount, (float) $advance->balance));

                return ['name' => 'Advance · '.$advance->title, 'amount' => round($take, 2)];
            })
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->values()
            ->all();
    }
}
