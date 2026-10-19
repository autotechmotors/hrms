<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Department;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $start = Carbon::parse($request->input('week', now()->startOfWeek()->toDateString()))->startOfWeek()->startOfDay();
        $dates = collect(range(0, 6))->map(fn (int $offset) => $start->copy()->addDays($offset));
        $end = $dates->last();

        $employeesQuery = $this->activeEmployees()->with(['department', 'designation']);
        $this->applyEmployeeFilters($employeesQuery, $request);
        $employees = $employeesQuery->get();

        $shifts = Shift::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->orderBy('name')
            ->get();

        $rosters = ShiftRoster::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (ShiftRoster $roster) => $roster->employee_id.'|'.$roster->work_date->toDateString());

        $stats = $this->weekStats($employees->count(), $dates->count(), $rosters, $shifts->count());

        return view('operations.shifts.index', [
            'start' => $start,
            'end' => $end,
            'dates' => $dates,
            'employees' => $employees,
            'rosters' => $rosters,
            'shifts' => $shifts,
            'stats' => $stats,
            'departments' => Department::query()->visibleTo($request->user())->orderBy('name')->get(),
            'filters' => [
                'q' => trim((string) $request->input('q', '')),
                'department_id' => $request->input('department_id'),
            ],
            'canEdit' => $request->user()->hasPermission('employees.edit'),
            'isThisWeek' => $start->toDateString() === now()->startOfWeek()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $companyId = $this->companyId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('shifts', 'code')->where('company_id', $companyId)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_flexible' => ['nullable', 'boolean'],
        ]);

        Shift::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'break_minutes' => $data['break_minutes'] ?? 0,
            'is_flexible' => $request->boolean('is_flexible'),
            'is_active' => true,
        ]);

        return redirect()->route('shifts.index', $this->weekQuery($request))->with('success', 'Shift added.');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorizePeople();
        abort_unless((int) $shift->company_id === $this->companyId(), 404);
        $shift->delete();

        return redirect()->route('shifts.index', $this->weekQuery($request))->with('success', 'Shift removed.');
    }

    public function roster(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer'],
            'is_off' => ['nullable', 'boolean'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $off = $request->boolean('is_off');
        $shiftId = $off ? null : ($data['shift_id'] ?? null);
        if ($shiftId) {
            Shift::query()->visibleTo($request->user())->where('company_id', $this->companyId())->findOrFail($shiftId);
        }

        if (! $shiftId && ! $off) {
            ShiftRoster::query()->where('employee_id', $employee->id)->whereDate('work_date', $data['work_date'])->delete();
        } else {
            ShiftRoster::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $data['work_date']],
                ['shift_id' => $shiftId, 'is_off' => $off],
            );
        }

        return redirect()->route('shifts.index', $this->weekQuery($request))->with('success', 'Roster updated.');
    }

    public function rosterBulk(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'week' => ['required', 'date'],
            'employee_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'mode' => ['required', Rule::in(['shift', 'off', 'clear'])],
            'weekdays_only' => ['nullable', 'boolean'],
        ]);

        $start = Carbon::parse($data['week'])->startOfWeek()->startOfDay();
        $dates = collect(range(0, 6))
            ->map(fn (int $offset) => $start->copy()->addDays($offset))
            ->when($request->boolean('weekdays_only'), fn ($days) => $days->reject(fn (Carbon $day) => $day->isWeekend())->values());

        $employeesQuery = $this->activeEmployees();
        $this->applyEmployeeFilters($employeesQuery, $request);
        if (! empty($data['employee_id'])) {
            $employeesQuery->whereKey($data['employee_id']);
        }
        $employeeIds = $employeesQuery->pluck('id');
        abort_if($employeeIds->isEmpty(), 404);

        $mode = $data['mode'];
        $shiftId = null;
        if ($mode === 'shift') {
            $shiftId = $data['shift_id'] ?? null;
            if (! $shiftId) {
                return back()->with('error', 'Select a shift before applying to the week.');
            }
            Shift::query()->visibleTo($request->user())->where('company_id', $this->companyId())->findOrFail($shiftId);
        }

        foreach ($employeeIds as $employeeId) {
            foreach ($dates as $day) {
                $date = $day->toDateString();
                if ($mode === 'clear') {
                    ShiftRoster::query()->where('employee_id', $employeeId)->whereDate('work_date', $date)->delete();

                    continue;
                }

                ShiftRoster::query()->updateOrCreate(
                    ['employee_id' => $employeeId, 'work_date' => $date],
                    [
                        'shift_id' => $mode === 'off' ? null : $shiftId,
                        'is_off' => $mode === 'off',
                    ],
                );
            }
        }

        $scope = $request->filled('employee_id') ? 'this employee' : 'all listed people';
        $label = match ($mode) {
            'off' => 'Marked off',
            'clear' => 'Cleared',
            default => 'Applied shift',
        };

        return redirect()->route('shifts.index', $this->weekQuery($request))
            ->with('success', "{$label} for {$scope} across the week.");
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
     * @param  Collection<string, Collection<int, ShiftRoster>>  $rosters
     * @return array<string, int>
     */
    protected function weekStats(int $employeeCount, int $dayCount, $rosters, int $shiftCount): array
    {
        $assigned = 0;
        $off = 0;
        foreach ($rosters as $group) {
            $roster = $group->first();
            if (! $roster) {
                continue;
            }
            if ($roster->is_off) {
                $off++;
            } elseif ($roster->shift_id) {
                $assigned++;
            }
        }

        $slots = $employeeCount * $dayCount;

        return [
            'shifts' => $shiftCount,
            'assigned' => $assigned,
            'off' => $off,
            'open' => max(0, $slots - $assigned - $off),
            'people' => $employeeCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function weekQuery(Request $request): array
    {
        return array_filter([
            'week' => $request->input('week'),
            'q' => $request->input('q'),
            'department_id' => $request->input('department_id'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
