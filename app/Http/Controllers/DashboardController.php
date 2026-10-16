<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeExit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        abort_unless($user->hasPermission('dashboard.view'), 403);
        $employees = Employee::query()->visibleTo($user);
        $active = (clone $employees)->whereHas('employmentStatus', fn ($query) => $query->where('is_exit', false));
        $exited = (clone $employees)->whereHas('employmentStatus', fn ($query) => $query->where('is_exit', true));

        return view('dashboard', [
            'totalEmployees' => (clone $employees)->count(),
            'activeEmployees' => (clone $active)->count(),
            'exitedEmployees' => (clone $exited)->count(),
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
                ->map(function (Department $department) use ($employees) {
                    $department->total = (clone $employees)->where('department_id', $department->id)->count();

                    return $department;
                }),
            'recentAudits' => $user->hasPermission('audit_logs.view')
                ? AuditLog::query()->with('user')->latest()->limit(8)->get()
                : collect(),
        ]);
    }
}
