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
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        abort_unless($user->hasPermission('dashboard.view'), 403);
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

        return view('dashboard', [
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
                ? AuditLog::query()->with('user')->latest()->limit(8)->get()
                : collect(),
            'gatePassPending' => $user->hasPermission('gate_passes.view')
                ? GatePass::query()->forEmployees((clone $employees)->select('employees.id'))->pending()->count()
                : null,
            'gatePassActive' => $user->hasPermission('gate_passes.view')
                ? GatePass::query()->forEmployees((clone $employees)->select('employees.id'))->active()->count()
                : null,
            'blockedEmployees' => (clone $employees)
                ->where('is_blocked', true)
                ->where(fn (Builder $query) => $this->stillEmployed($query))
                ->count(),
            'pulse' => $user->hasPermission('attendance.view')
                ? $this->pulse((clone $employees)->where(fn (Builder $query) => $this->stillEmployed($query)))
                : null,
        ]);
    }

    private function stillEmployed(Builder $query): void
    {
        $query->whereDoesntHave('employmentStatus')
            ->orWhereHas('employmentStatus', fn (Builder $status) => $status->where('is_exit', false));
    }

    /**
     * @return array{active: int, today: list<array{key: string, label: string, total: int, tone: string}>, week: list<array{label: string, present: int, absent: int, other: int}>, shifts: array{day: int, night: int}}
     */
    private function pulse(Builder $active): array
    {
        $activeCount = (clone $active)->count();
        $today = AttendanceDay::query()
            ->whereIn('employee_id', (clone $active)->select('employees.id'))
            ->whereDate('work_date', today())
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

        $from = today()->subDays(6);
        $weekRows = AttendanceDay::query()
            ->whereIn('employee_id', (clone $active)->select('employees.id'))
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', today())
            ->selectRaw('work_date, status, count(*) as total')
            ->groupBy('work_date', 'status')
            ->get();
        $byDate = [];
        foreach ($weekRows as $row) {
            $date = Carbon::parse($row->work_date)->toDateString();
            $byDate[$date][$row->status] = (int) $row->total;
        }
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $from->copy()->addDays($i);
            $bucket = $byDate[$date->toDateString()] ?? [];
            $present = (int) ($bucket['present'] ?? 0);
            $absent = (int) ($bucket['absent'] ?? 0);
            $other = array_sum($bucket) - $present - $absent;
            $week[] = [
                'label' => $date->translatedFormat('D'),
                'present' => $present,
                'absent' => $absent,
                'other' => max(0, $other),
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
