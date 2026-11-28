<?php

namespace App\Services\Operations;

use App\Models\Advance;
use App\Models\Employee;
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

    public function refreshLine(SalarySheetLine $line): SalarySheetLine
    {
        $line->loadMissing('sheet', 'employee');
        $sheet = $line->sheet;
        abort_unless($sheet && $line->employee, 404);
        $payload = $this->line(
            (int) $sheet->id,
            $line->employee,
            Carbon::parse($sheet->starts_on)->startOfDay(),
            Carbon::parse($sheet->ends_on)->startOfDay(),
        );
        unset($payload['salary_sheet_id'], $payload['employee_id']);
        $line->update($payload);

        return $line->fresh();
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
        $deductionLines = [];
        $deductions = 0.0;
        $advances = $this->advances($employee, $from, $to);
        $advanceTotal = round(array_sum(array_column($advances, 'amount')), 2);
        $overtimePay = round((float) $pay['overtime_pay'], 2);
        $net = max(0, round($earned - $deductions - $advanceTotal + $overtimePay, 2));

        return [
            'salary_sheet_id' => $sheetId,
            'employee_id' => $employee->id,
            'monthly_salary' => $monthly,
            'hours_per_day' => $hoursPerDay,
            'hourly_rate' => round($rate, 4),
            'attended_minutes' => $attended,
            'paid_minutes' => $paid,
            'ot_minutes' => (int) $pay['overtime_minutes'],
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
                'overtime' => $pay['overtime'],
                'overtime_pay' => $overtimePay,
                'computed_net' => $net,
            ],
        ];
    }

    public function settleAdvances(SalarySheet $sheet): void
    {
        $sheet->loadMissing('lines');
        foreach ($sheet->lines as $line) {
            Advance::query()
                ->where('employee_id', $line->employee_id)
                ->where('status', 'active')
                ->whereDate('starts_on', '>=', $sheet->starts_on)
                ->whereDate('starts_on', '<=', $sheet->ends_on)
                ->orderBy('id')
                ->get()
                ->each(function (Advance $advance): void {
                    $take = max(0, min((float) $advance->amount, (float) $advance->balance));
                    if ($take <= 0) {
                        return;
                    }
                    $advance->balance = 0;
                    $advance->status = 'completed';
                    $advance->save();
                });
        }
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
