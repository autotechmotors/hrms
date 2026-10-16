<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $month = Carbon::create(
            (int) $request->input('year', now()->year),
            (int) $request->input('month', now()->month),
            1,
        )->startOfMonth();
        $companyId = $this->companyId();
        $employees = Employee::query()->visibleTo($request->user())->where('company_id', $companyId);
        $ids = (clone $employees)->pluck('id');

        $headcount = Department::query()
            ->visibleTo($request->user())
            ->where('company_id', $companyId)
            ->withCount(['employees' => fn ($query) => $query->visibleTo($request->user())])
            ->orderBy('name')
            ->get();

        $attendance = AttendanceDay::query()
            ->whereIn('employee_id', $ids)
            ->whereBetween('work_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $leave = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth())
            ->whereDate('ends_on', '>=', $month)
            ->with('leaveType')
            ->get()
            ->groupBy(fn (LeaveRequest $row) => $row->leaveType?->name ?? 'Leave')
            ->map(fn ($rows) => $rows->sum('days'));

        $late = AttendanceDay::query()
            ->whereIn('employee_id', $ids)
            ->whereBetween('work_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->where('status', 'late')
            ->with('employee')
            ->orderBy('work_date')
            ->limit(100)
            ->get();

        $payroll = PayrollRun::query()
            ->visibleTo($request->user())
            ->where('company_id', $companyId)
            ->where('period_year', $month->year)
            ->where('period_month', $month->month)
            ->withSum('items', 'net')
            ->withSum('items', 'gross')
            ->withSum('items', 'deductions')
            ->first();

        return view('operations.reports.index', [
            'month' => $month,
            'headcount' => $headcount,
            'attendance' => $attendance,
            'leave' => $leave,
            'late' => $late,
            'payroll' => $payroll,
            'people' => (clone $employees)->count(),
        ]);
    }
}
