<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Services\Operations\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $date = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();
        $companyId = $this->companyId();
        $employees = $this->activeEmployees()->with(['department', 'designation'])->paginate(30)->withQueryString();
        $picker = $this->activeEmployees()->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']);
        $days = AttendanceDay::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('employee_id');

        return view('operations.attendance.index', [
            'date' => $date,
            'employees' => $employees,
            'picker' => $picker,
            'days' => $days,
            'rule' => AttendanceRule::query()->firstOrCreate(
                ['company_id' => $companyId],
                ['grace_minutes' => 0, 'mark_absent_without_punch' => true],
            ),
            'canEdit' => $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function updateRule(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'full_day_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'mark_absent_without_punch' => ['nullable', 'boolean'],
        ]);

        AttendanceRule::query()->updateOrCreate(
            ['company_id' => $this->companyId()],
            [
                'grace_minutes' => $data['grace_minutes'],
                'half_day_minutes' => $data['half_day_minutes'] ?: null,
                'full_day_minutes' => $data['full_day_minutes'] ?: null,
                'mark_absent_without_punch' => $request->boolean('mark_absent_without_punch'),
            ],
        );

        return back()->with('success', 'Attendance rule saved.');
    }

    public function punch(Request $request, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'punch_in' => ['nullable', 'date_format:H:i'],
            'punch_out' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $data['work_date'])
            ->first() ?? new AttendanceDay([
                'employee_id' => $employee->id,
                'work_date' => $data['work_date'],
            ]);
        $day->fill([
            'punch_in' => $data['punch_in'] ?: null,
            'punch_out' => $data['punch_out'] ?: null,
            'remarks' => $data['remarks'] ?? null,
            'source' => 'manual',
            'status' => $day->status ?: 'absent',
        ])->save();
        $calculator->recalculate($employee, Carbon::parse($data['work_date']));

        return redirect()->route('attendance.index', ['date' => $data['work_date']])->with('success', 'Punch saved.');
    }

    public function recalculate(Request $request, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate(['work_date' => ['required', 'date']]);
        $date = Carbon::parse($data['work_date'])->startOfDay();
        $this->activeEmployees()->each(fn ($employee) => $calculator->recalculate($employee, $date));

        return redirect()->route('attendance.index', ['date' => $date->toDateString()])->with('success', 'Attendance calculated from punches, shifts, leave, and holidays.');
    }
}
