<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\AttendanceDay;
use App\Models\AttendanceRule;
use App\Models\Department;
use App\Services\Operations\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $date = Carbon::parse($request->input('date', now()->toDateString()))->startOfDay();
        $companyId = $this->companyId();
        $canEdit = $request->user()->hasPermission('employees.edit');
        $status = trim((string) $request->input('status', ''));

        $filtered = $this->activeEmployees()->with(['department', 'designation']);
        $this->applyEmployeeFilters($filtered, $request);
        $scopedIds = (clone $filtered)->pluck('id');

        $allDays = AttendanceDay::query()
            ->whereIn('employee_id', $scopedIds)
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('employee_id');

        if ($status === 'pending') {
            $filtered->whereNotIn('id', $allDays->keys()->all());
        } elseif ($status !== '') {
            $matched = $allDays->filter(fn (AttendanceDay $day) => $day->status === $status)->keys()->all();
            $filtered->whereIn('id', $matched ?: [0]);
        }

        $employees = $filtered->paginate(30)->withQueryString();
        $stats = $this->dayStats($scopedIds->count(), $allDays);

        return view('operations.attendance.index', [
            'date' => $date,
            'employees' => $employees,
            'picker' => $this->activeEmployees()->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']),
            'days' => $allDays,
            'stats' => $stats,
            'departments' => Department::query()->visibleTo($request->user())->orderBy('name')->get(),
            'filters' => [
                'q' => trim((string) $request->input('q', '')),
                'department_id' => $request->input('department_id'),
                'status' => $status,
            ],
            'rule' => AttendanceRule::query()->firstOrCreate(
                ['company_id' => $companyId],
                ['grace_minutes' => 0, 'mark_absent_without_punch' => true],
            ),
            'canEdit' => $canEdit,
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

        return redirect()->route('attendance.index', array_filter([
            'date' => $data['work_date'],
            'q' => $request->input('q'),
            'department_id' => $request->input('department_id'),
            'status' => $request->input('status'),
        ]))->with('success', 'Punch saved.');
    }

    public function recalculate(Request $request, AttendanceCalculator $calculator): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate(['work_date' => ['required', 'date']]);
        $date = Carbon::parse($data['work_date'])->startOfDay();
        $this->activeEmployees()->each(fn ($employee) => $calculator->recalculate($employee, $date));

        return redirect()->route('attendance.index', ['date' => $date->toDateString()])->with('success', 'Attendance calculated from punches, shifts, leave, and holidays.');
    }

    protected function applyEmployeeFilters(Builder $query, Request $request): void
    {
        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('employee_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
    }

    /**
     * @param  Collection<int, AttendanceDay>  $days
     * @return array<string, int>
     */
    protected function dayStats(int $totalEmployees, Collection $days): array
    {
        $counts = [
            'present' => 0,
            'late' => 0,
            'half_day' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'holiday' => 0,
            'weekly_off' => 0,
            'unmarked' => 0,
            'pending' => max(0, $totalEmployees - $days->count()),
        ];

        foreach ($days as $day) {
            if (array_key_exists($day->status, $counts)) {
                $counts[$day->status]++;
            }
        }

        $counts['working'] = $counts['present'] + $counts['late'] + $counts['half_day'];

        return $counts;
    }
}
