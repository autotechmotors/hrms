<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\EmployeeWorkHour;
use App\Models\GatePass;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|Response
    {
        $user = request()->user();
        abort_unless($user->hasPermission('dashboard.view'), 403);
        [$from, $to] = $this->range();
        $employees = Employee::query()->visibleTo($user);
        $headcount = (clone $employees)
            ->leftJoin('employment_statuses', 'employment_statuses.id', '=', 'employees.employment_status_id')
            ->selectRaw('count(employees.id) as total')
            ->selectRaw('sum(case when employment_statuses.is_exit = 0 then 1 else 0 end) as active_total')
            ->selectRaw('sum(case when employment_statuses.is_exit = 1 then 1 else 0 end) as exited_total')
            ->first();
        $byDepartment = (clone $employees)
            ->selectRaw('employees.department_id as department_id, count(*) as total')
            ->groupBy('employees.department_id')
            ->pluck('total', 'department_id');

        $data = [
            'totalEmployees' => (int) $headcount->total,
            'activeEmployees' => (int) $headcount->active_total,
            'exitedEmployees' => (int) $headcount->exited_total,
            'departments' => Department::query()->visibleTo($user)->count(),
            'openExits' => EmployeeExit::query()
                ->whereIn('employee_id', (clone $employees)->select('employees.id'))
                ->where('fnf_status', '!=', 'completed')
                ->count(),
            'recentEmployees' => (clone $employees)->with(['department', 'designation', 'employmentStatus'])->latest()->limit(6)->get(),
            'departmentRows' => Department::query()
                ->visibleTo($user)
                ->orderBy('name')
                ->get()
                ->each(function (Department $department) use ($byDepartment): void {
                    $department->total = (int) ($byDepartment[$department->id] ?? 0);
                })
                ->sortByDesc('total')
                ->values(),
            'recentAudits' => $user->hasPermission('audit_logs.view')
                ? AuditLog::query()->with('user')
                    ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->latest()
                    ->limit(8)
                    ->get()
                : collect(),
            'gatePassPending' => $user->hasPermission('gate_passes.view')
                ? $this->passesInRange((clone $employees)->select('employees.id'), $from, $to)->pending()->count()
                : null,
            'gatePassActive' => $user->hasPermission('gate_passes.view')
                ? $this->passesInRange((clone $employees)->select('employees.id'), $from, $to)->active()->count()
                : null,
            'blockedEmployees' => (clone $employees)
                ->where('is_blocked', true)
                ->where(fn (Builder $query) => $this->stillEmployed($query))
                ->count(),
            'pulse' => $user->hasPermission('attendance.view')
                ? $this->pulse((clone $employees)->where(fn (Builder $query) => $this->stillEmployed($query)), $from, $to)
                : null,
            'rangeFrom' => $from,
            'rangeTo' => $to,
            'rangePreset' => $this->preset($from, $to),
        ];

        if (request()->ajax() && request()->boolean('partial')) {
            return response()->view('dashboard._panel', $data);
        }

        return view('dashboard', $data);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(): array
    {
        $today = today();
        $to = $this->day(request('to')) ?? $today->copy();
        $from = $this->day(request('from')) ?? $to->copy()->subDays(6);
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }
        if ($to->greaterThan($today)) {
            $to = $today->copy();
        }
        if ($from->greaterThan($to)) {
            $from = $to->copy();
        }
        if (abs((int) $from->diffInDays($to)) > 30) {
            $from = $to->copy()->subDays(30);
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    private function day(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function preset(Carbon $from, Carbon $to): string
    {
        if (! $to->isToday()) {
            return 'custom';
        }
        if ($from->isToday()) {
            return 'today';
        }
        if ($from->equalTo($to->copy()->subDays(6))) {
            return '7';
        }
        if ($from->equalTo($to->copy()->subDays(29))) {
            return '30';
        }

        return 'custom';
    }

    private function passesInRange(Builder $employeeIds, Carbon $from, Carbon $to): Builder
    {
        return GatePass::query()
            ->forEmployees($employeeIds)
            ->where(function (Builder $query) use ($from, $to): void {
                $query->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
            });
    }

    private function stillEmployed(Builder $query): void
    {
        $query->whereDoesntHave('employmentStatus')
            ->orWhereHas('employmentStatus', fn (Builder $status) => $status->where('is_exit', false));
    }

    /**
     * @return array{active: int, today: list<array{key: string, label: string, total: int, tone: string}>, week: list<array{label: string, present: int, absent: int, other: int}>, shifts: array{day: int, night: int}}
     */
    private function pulse(Builder $active, Carbon $from, Carbon $to): array
    {
        $activeCount = (clone $active)->count();
        $today = AttendanceDay::query()
            ->whereIn('employee_id', (clone $active)->select('employees.id'))
            ->whereDate('work_date', $to->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $known = [
            'present' => ['Present', 'ok'],
            'half_day' => ['Half day', 'wn'],
            'absent' => ['Absent', 'er'],
            'on_leave' => ['On leave', 'wn'],
            'holiday' => ['Holiday', 'mu'],
            'weekly_off' => ['Weekly off', 'mu'],
        ];
        $rows = [];
        $seen = 0;
        foreach ($known as $key => [$label, $tone]) {
            $total = (int) ($today[$key] ?? 0);
            $seen += $total;
            $rows[] = ['key' => $key, 'label' => $label, 'total' => $total, 'tone' => $tone];
        }
        $rows[] = [
            'key' => 'unmarked',
            'label' => 'Not marked',
            'total' => max(0, $activeCount - $seen),
            'tone' => 'mu',
        ];

        $weekRows = AttendanceDay::query()
            ->whereIn('employee_id', (clone $active)->select('employees.id'))
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->selectRaw('work_date, status, count(*) as total')
            ->groupBy('work_date', 'status')
            ->get();
        $byDate = [];
        foreach ($weekRows as $row) {
            $date = Carbon::parse($row->work_date)->toDateString();
            $byDate[$date][$row->status] = (int) $row->total;
        }
        $span = abs((int) $from->diffInDays($to)) + 1;
        $short = $span > 10;
        $week = [];
        for ($i = 0; $i < $span; $i++) {
            $date = $from->copy()->addDays($i);
            $bucket = $byDate[$date->toDateString()] ?? [];
            $present = (int) ($bucket['present'] ?? 0);
            $absent = (int) ($bucket['absent'] ?? 0);
            $other = array_sum($bucket) - $present - $absent;
            $week[] = [
                'label' => $short ? $date->format('j') : $date->translatedFormat('D'),
                'month' => $date->translatedFormat('M'),
                'full' => $date->translatedFormat('D j M'),
                'present' => $present,
                'absent' => $absent,
                'other' => max(0, $other),
                'is_today' => $date->isToday(),
                'show_month' => $i === 0 || $date->day === 1,
            ];
        }

        $night = (int) EmployeeWorkHour::query()
            ->whereIn('employee_id', (clone $active)->select('employees.id'))
            ->where('shift_kind', 'night')
            ->count();

        return [
            'active' => $activeCount,
            'today' => $rows,
            'week' => $week,
            'shifts' => [
                'day' => max(0, $activeCount - $night),
                'night' => $night,
            ],
        ];
    }
}
