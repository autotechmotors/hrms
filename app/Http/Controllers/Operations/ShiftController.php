<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Department;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeAny('shifts.view');
        $start = Carbon::parse($request->input('week', now()->startOfWeek()->toDateString()))->startOfWeek()->startOfDay();
        $dates = collect(range(0, 6))->map(fn (int $offset) => $start->copy()->addDays($offset));
        $end = $dates->last();

        $employeesQuery = $this->activeEmployees()->with(['department', 'designation']);
        $this->applyEmployeeFilters($employeesQuery, $request);
        $partial = $request->ajax() || $request->boolean('partial');

        $shifts = Shift::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->orderBy('name')
            ->get();

        $stats = $this->rosterStats(clone $employeesQuery, $start, $end, $shifts->count());
        $employees = $employeesQuery->paginate(24)->withQueryString();
        $pageIds = $employees->getCollection()->pluck('id');

        $rosters = $pageIds->isEmpty()
            ? collect()
            : ShiftRoster::query()
                ->whereIn('employee_id', $pageIds)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->groupBy(fn (ShiftRoster $roster) => $roster->employee_id.'|'.$roster->work_date->toDateString());

        return view($partial ? 'operations.shifts._board' : 'operations.shifts.index', [
            'start' => $start,
            'end' => $end,
            'dates' => $dates,
            'employees' => $employees,
            'rosters' => $rosters,
            'shifts' => $shifts,
            'stats' => $stats,
            'departments' => $partial ? collect() : Department::query()->visibleTo($request->user())->orderBy('name')->get(),
            'filters' => [
                'q' => trim((string) $request->input('q', '')),
                'department_id' => $request->input('department_id'),
            ],
            'canEdit' => $request->user()->hasPermission('shifts.manage'),
            'isThisWeek' => $start->toDateString() === now()->startOfWeek()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAny('shifts.manage');
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
        $this->authorizeAny('shifts.manage');
        abort_unless((int) $shift->company_id === $this->companyId(), 404);
        $shift->delete();

        return redirect()->route('shifts.index', $this->weekQuery($request))->with('success', 'Shift removed.');
    }

    public function roster(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('shifts.manage');
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

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Roster updated.',
                'cell' => ['off' => $off, 'shift_id' => $shiftId ? (int) $shiftId : null],
                'stats' => $this->statsForRequest($request),
            ]);
        }

        return redirect()->route('shifts.index', $this->weekQuery($request))->with('success', 'Roster updated.');
    }

    public function rosterBulk(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('shifts.manage');
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
                if ($request->ajax()) {
                    return response()->json(['message' => 'Select a shift before applying to the week.'], 422);
                }

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

        $message = "{$label} for {$scope} across the week.";
        if ($request->ajax()) {
            return response()->json([
                'message' => $message,
                'stats' => $this->statsForRequest($request),
            ]);
        }

        return redirect()->route('shifts.index', $this->weekQuery($request))->with('success', $message);
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
     * @return array<string, int>
     */
    protected function rosterStats(Builder $employees, Carbon $start, Carbon $end, int $shiftCount): array
    {
        $ids = (clone $employees)->pluck('id');
        $people = $ids->count();
        $base = ShiftRoster::query()
            ->whereIn('employee_id', $ids->all() ?: [0])
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);
        $off = (clone $base)->where('is_off', true)->count();
        $assigned = (clone $base)->where('is_off', false)->whereNotNull('shift_id')->count();

        return [
            'shifts' => $shiftCount,
            'assigned' => $assigned,
            'off' => $off,
            'open' => max(0, ($people * 7) - $assigned - $off),
            'people' => $people,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function statsForRequest(Request $request): array
    {
        $start = Carbon::parse($request->input('week', now()->startOfWeek()->toDateString()))->startOfWeek()->startOfDay();
        $employees = $this->activeEmployees();
        $this->applyEmployeeFilters($employees, $request);
        $shiftCount = Shift::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->count();

        return $this->rosterStats($employees, $start, $start->copy()->addDays(6), $shiftCount);
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
