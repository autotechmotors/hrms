<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Department;
use App\Models\DepartmentWorkHour;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeWorkHour;
use App\Services\Operations\WorkHourResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HoursPayController extends Controller
{
    use UsesWorkspace;

    public function __construct(private readonly WorkHourResolver $hours) {}

    public function index(Request $request): View
    {
        $this->authorizeAny('salary.view');
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'department_id' => (string) $request->input('department_id', ''),
        ];

        $people = $this->people($request, $filters);

        if ($request->boolean('partial') || $request->ajax()) {
            return view('operations.hours-pay._people', [
                'people' => $people,
                'filters' => $filters,
            ]);
        }

        $counts = $this->peopleQuery()
            ->selectRaw('department_id, count(*) as aggregate')
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        $departments = Department::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->with('workHour')
            ->orderBy('name')
            ->get()
            ->map(function (Department $department) use ($counts) {
                $value = $department->workHour?->hours_per_day;
                $window = $department->workHour;

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'people' => (int) ($counts[$department->id] ?? 0),
                    'hours' => $this->windowLabel($window),
                    'value' => $value !== null ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') : '9',
                    'weekday_start' => $this->hours->clock($window?->weekday_start, '09:00'),
                    'weekday_end' => $this->hours->clock($window?->weekday_end, '18:00'),
                    'sunday_start' => $this->hours->clock($window?->sunday_start, '08:00'),
                    'sunday_end' => $this->hours->clock($window?->sunday_end, '13:00'),
                    'night_weekday_start' => $this->hours->clock($window?->night_weekday_start, '21:00'),
                    'night_weekday_end' => $this->hours->clock($window?->night_weekday_end, '06:00'),
                    'weekday_break_start' => $this->hours->clockOrEmpty($window?->weekday_break_start),
                    'weekday_break_end' => $this->hours->clockOrEmpty($window?->weekday_break_end),
                    'sunday_break_start' => $this->hours->clockOrEmpty($window?->sunday_break_start),
                    'sunday_break_end' => $this->hours->clockOrEmpty($window?->sunday_break_end),
                    'night_weekday_break_start' => $this->hours->clockOrEmpty($window?->night_weekday_break_start),
                    'night_weekday_break_end' => $this->hours->clockOrEmpty($window?->night_weekday_break_end),
                    'night_sunday_start' => $this->hours->clock($window?->night_sunday_start, '21:00'),
                    'night_sunday_end' => $this->hours->clock($window?->night_sunday_end, '02:00'),
                    'night_sunday_break_start' => $this->hours->clockOrEmpty($window?->night_sunday_break_start) ?: '01:00',
                    'night_sunday_break_end' => $this->hours->clockOrEmpty($window?->night_sunday_break_end) ?: '02:00',
                    'night_works_sunday' => (bool) $window?->night_works_sunday,
                ];
            });

        return view('operations.hours-pay.index', [
            'departments' => $departments,
            'people' => $people,
            'filters' => $filters,
            'canEdit' => $request->user()->hasPermission('salary.manage'),
        ]);
    }

    public function show(Request $request, Employee $employee): View
    {
        $this->authorizeAny('salary.view');
        $employee = $this->visibleEmployee($request, $employee);

        return view('operations.hours-pay.show', $this->detail($request, $employee));
    }

    public function storeDepartment(Request $request, Department $department): JsonResponse
    {
        $this->authorizeAny('salary.manage');
        abort_unless(
            Department::query()->visibleTo($request->user())->where('company_id', $this->companyId())->whereKey($department->id)->exists(),
            404
        );

        $data = $this->windowInput($request) + $this->nightSundayInput($request);
        $hours = $this->hours->hoursFromWindow($data['weekday_start'], $data['weekday_end']);

        $row = DepartmentWorkHour::query()->updateOrCreate(
            ['department_id' => $department->id],
            $data + ['hours_per_day' => $hours]
        );

        return response()->json([
            'message' => $department->name.' now uses '.$this->windowLabel($row).'.',
            'hours' => $this->windowLabel($row),
            'value' => rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.'),
        ]);
    }

    public function storeEmployeeHours(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeAny('salary.manage');
        $employee = $this->visibleEmployee($request, $employee);
        $kind = $request->validate([
            'mode' => ['required', Rule::in(['department', 'own'])],
            'shift_kind' => ['required', Rule::in(['day', 'night'])],
        ])['shift_kind'];

        $payload = [
            'uses_department' => $request->input('mode') === 'department',
            'shift_kind' => $kind,
        ];
        if ($request->input('mode') === 'own') {
            $times = $this->windowInput($request);
            $payload = $times + [
                'uses_department' => false,
                'shift_kind' => $kind,
                'hours_per_day' => $this->hours->hoursFromWindow($times['weekday_start'], $times['weekday_end']),
            ];
            if ($kind === 'night') {
                $sunday = $this->nightSundayInput($request);
                unset($sunday['night_works_sunday']);
                $payload['works_sunday'] = $request->boolean('works_sunday');
                if ($payload['works_sunday']) {
                    $payload = $sunday + $payload;
                }
            }
        }

        EmployeeWorkHour::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            $payload
        );

        $employee->unsetRelation('workHour');

        return response()->json([
            'message' => 'Working hours saved for '.$employee->full_name.'.',
            'html' => view('operations.hours-pay._detail', $this->detail($request, $employee))->render(),
        ]);
    }

    public function storeEmployeeSalary(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeAny('salary.manage');
        $employee = $this->visibleEmployee($request, $employee);
        if ($employee->isPieceSalary()) {
            return response()->json([
                'message' => 'Piece based people stay on attendance only. Monthly salary is not set for them.',
            ], 422);
        }
        if (! $employee->isHoursSalary()) {
            return response()->json([
                'message' => 'Set salary type to Hours based on the employee profile before saving a monthly amount.',
            ], 422);
        }
        $data = $request->validate([
            'gross_monthly' => ['required', 'numeric', 'min:0.01'],
            'effective_from' => ['required', 'date'],
        ], [
            'gross_monthly.required' => 'Enter the monthly salary.',
            'gross_monthly.min' => 'Enter a monthly salary greater than zero.',
            'effective_from.required' => 'Choose the date this salary starts.',
        ]);

        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'gross_monthly' => $data['gross_monthly'],
            'effective_from' => $data['effective_from'],
        ]);

        return response()->json([
            'message' => 'Monthly salary saved. Salary sheets use the amount that is already effective on the pay date.',
            'html' => view('operations.hours-pay._detail', $this->detail($request, $employee))->render(),
        ]);
    }

    /**
     * @param  array{q: string, department_id: string}  $filters
     */
    private function people(Request $request, array $filters)
    {
        return $this->peopleQuery()
            ->with(['department.workHour', 'workHour', 'currentSalary'])
            ->when($filters['department_id'] !== '', fn (Builder $query) => $query->where('department_id', (int) $filters['department_id']))
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['q'].'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('employee_code', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->appends($request->except(['page', 'partial']))
            ->through(function (Employee $employee) {
                $resolved = $this->hours->resolve($employee->department?->workHour, $employee->workHour);
                $salary = $employee->currentSalary;

                return [
                    'url' => route('hours-pay.show', $employee),
                    'name' => $employee->full_name,
                    'code' => $employee->employee_code,
                    'department' => $employee->department?->name ?? 'No department',
                    'hours' => $this->hours->label($resolved['hours']),
                    'shift' => $employee->workHour?->shift_kind === 'night' ? 'Night' : 'Day',
                    'source' => $resolved['source'] === 'unset' ? 'Not chosen yet' : $this->hours->sourceLabel($resolved['source']),
                    'salary' => $employee->isPieceSalary()
                        ? 'Piece based'
                        : ($salary ? '₹'.number_format((float) $salary->gross_monthly, 2) : 'Not set'),
                    'salaryNote' => $employee->isPieceSalary()
                        ? 'Attendance only'
                        : ($salary ? 'a month' : 'No salary yet'),
                    'salaryType' => $employee->salaryTypeLabel(),
                ];
            });
    }

    private function peopleQuery(): Builder
    {
        return Employee::query()
            ->visibleTo(request()->user())
            ->where('company_id', $this->companyId())
            ->where('is_blocked', false)
            ->whereHas('employmentStatus', fn (Builder $query) => $query->where('is_exit', false));
    }

    private function visibleEmployee(Request $request, Employee $employee): Employee
    {
        abort_unless($employee->company_id === $this->companyId(), 404);
        abort_unless(
            Employee::query()->visibleTo($request->user())->whereKey($employee->id)->exists(),
            404
        );

        return $employee->load(['department.workHour', 'workHour']);
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Request $request, Employee $employee): array
    {
        $resolved = $this->hours->resolve($employee->department?->workHour, $employee->workHour);
        $history = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->with('structure')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
        $current = $history->first(fn (EmployeeSalary $row) => $row->effective_from->lte(today()));
        $departmentName = $employee->department?->name ?? 'their department';

        $sentence = match ($resolved['source']) {
            'own' => $employee->full_name.' works '.$this->hours->label($resolved['hours']).' a day. '.$departmentName.' is '.$this->hours->label($resolved['department_hours']).'.',
            'department' => $employee->full_name.' uses '.$departmentName.' hours: '.$this->hours->label($resolved['hours']).' a day.',
            default => $departmentName.' has no hours yet. Set the department, or give this person their own hours.',
        };

        return [
            'employee' => $employee,
            'resolved' => $resolved,
            'hoursLabel' => $this->hours->label($resolved['hours']),
            'sourceLabel' => $this->hours->sourceLabel($resolved['source']),
            'departmentLabel' => $this->hours->label($resolved['department_hours']),
            'sentence' => $sentence,
            'current' => $current,
            'history' => $history,
            'canEdit' => $request->user()->hasPermission('salary.manage'),
            'mode' => $employee->workHour && ! $employee->workHour->uses_department ? 'own' : 'department',
            'ownHours' => $employee->workHour && ! $employee->workHour->uses_department
                ? rtrim(rtrim(number_format((float) $employee->workHour->hours_per_day, 2, '.', ''), '0'), '.')
                : '',
            'windows' => $this->formWindows($employee),
            'departmentWindows' => $this->windowsFrom($employee->department?->workHour),
            'shiftKind' => $employee->workHour?->shift_kind === 'night' ? 'night' : 'day',
        ];
    }

    /**
     * @return array{weekday_start: string, weekday_end: string, sunday_start: string, sunday_end: string}
     */
    private function windowInput(Request $request): array
    {
        $data = $request->validate([
            'weekday_start' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'weekday_end' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'sunday_start' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'sunday_end' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'night_weekday_start' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'night_weekday_end' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'weekday_break_start' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'weekday_break_end' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'sunday_break_start' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'sunday_break_end' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'night_weekday_break_start' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'night_weekday_break_end' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ], [
            'weekday_start.required' => 'Enter the day Monday to Saturday start.',
            'weekday_end.required' => 'Enter the day Monday to Saturday end.',
            'sunday_start.required' => 'Enter the day Sunday start.',
            'sunday_end.required' => 'Enter the day Sunday end.',
            'night_weekday_start.required' => 'Enter the night Monday to Saturday start.',
            'night_weekday_end.required' => 'Enter the night Monday to Saturday end.',
        ]);

        $times = [
            'weekday_start' => $this->hours->clock($data['weekday_start'], '09:00'),
            'weekday_end' => $this->hours->clock($data['weekday_end'], '18:00'),
            'sunday_start' => $this->hours->clock($data['sunday_start'], '08:00'),
            'sunday_end' => $this->hours->clock($data['sunday_end'], '13:00'),
            'night_weekday_start' => $this->hours->clock($data['night_weekday_start'], '21:00'),
            'night_weekday_end' => $this->hours->clock($data['night_weekday_end'], '06:00'),
        ];

        $this->assertWindow($times['weekday_start'], $times['weekday_end'], 'weekday_end', 'Day Monday to Saturday');
        $this->assertWindow($times['sunday_start'], $times['sunday_end'], 'sunday_end', 'Day Sunday');
        $this->assertWindow($times['night_weekday_start'], $times['night_weekday_end'], 'night_weekday_end', 'Night Monday to Saturday');
        $times = array_merge($times, $this->breakPair($request, 'weekday_break_start', 'weekday_break_end', $times['weekday_start'], $times['weekday_end'], 'weekday_break_end', 'Day Monday to Saturday break'));
        $times = array_merge($times, $this->breakPair($request, 'sunday_break_start', 'sunday_break_end', $times['sunday_start'], $times['sunday_end'], 'sunday_break_end', 'Day Sunday break'));
        $times = array_merge($times, $this->breakPair($request, 'night_weekday_break_start', 'night_weekday_break_end', $times['night_weekday_start'], $times['night_weekday_end'], 'night_weekday_break_end', 'Night Monday to Saturday break'));

        return $times;
    }

    /**
     * Sunday night on/off belongs to the department. Off leaves the stored clock alone.
     *
     * @return array<string, bool|string|null>
     */
    private function nightSundayInput(Request $request): array
    {
        if (! $request->boolean('night_works_sunday')) {
            return ['night_works_sunday' => false];
        }

        $data = $request->validate([
            'night_sunday_start' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'night_sunday_end' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'night_sunday_break_start' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'night_sunday_break_end' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ], [
            'night_sunday_start.required' => 'Enter the Sunday night start.',
            'night_sunday_end.required' => 'Enter the Sunday night end.',
            'night_sunday_break_start.required' => 'Enter the Sunday night break start.',
            'night_sunday_break_end.required' => 'Enter the Sunday night break end.',
        ]);

        $start = $this->hours->clock($data['night_sunday_start'], '21:00');
        $end = $this->hours->clock($data['night_sunday_end'], '02:00');
        $this->assertWindow($start, $end, 'night_sunday_end', 'Sunday night');
        $break = $this->breakPair($request, 'night_sunday_break_start', 'night_sunday_break_end', $start, $end, 'night_sunday_break_end', 'Sunday night break');

        return [
            'night_works_sunday' => true,
            'night_sunday_start' => $start,
            'night_sunday_end' => $end,
        ] + $break;
    }

    /**
     * @return array<string, ?string>
     */
    private function breakPair(Request $request, string $startKey, string $endKey, string $windowStart, string $windowEnd, string $field, string $label): array
    {
        $pair = $this->hours->pair($request->input($startKey), $request->input($endKey));
        if (($pair['start'] === null) !== ($pair['end'] === null)) {
            throw ValidationException::withMessages([
                $field => 'Enter both the break start and the break end.',
            ]);
        }
        if (! $this->hours->breakFits($windowStart, $windowEnd, $pair['start'], $pair['end'])) {
            throw ValidationException::withMessages([
                $field => $label.' must sit inside the working clock.',
            ]);
        }

        return [$startKey => $pair['start'], $endKey => $pair['end']];
    }

    private function assertWindow(string $start, string $end, string $field, string $label): void
    {
        if ($this->hours->span($start, $end) < 30) {
            throw ValidationException::withMessages([
                $field => $label.' must cover at least 30 minutes.',
            ]);
        }
    }

    private function windowLabel(?DepartmentWorkHour $row): string
    {
        if (! $row) {
            return 'Not set';
        }

        $week = $this->hours->clock($row->weekday_start, '09:00').'–'.$this->hours->clock($row->weekday_end, '18:00');
        $night = $this->hours->clock($row->night_weekday_start, '21:00').'–'.$this->hours->clock($row->night_weekday_end, '06:00');
        $sunday = $row->night_works_sunday
            ? ' · Sunday night '.$this->hours->clock($row->night_sunday_start, '21:00').'–'.$this->hours->clock($row->night_sunday_end, '02:00')
            : '';

        return 'Day '.$week.' · Night '.$night.$sunday;
    }

    /**
     * @return array{weekday_start: string, weekday_end: string, sunday_start: string, sunday_end: string}
     */
    private function formWindows(Employee $employee): array
    {
        $own = $employee->workHour && ! $employee->workHour->uses_department ? $employee->workHour : null;

        return $this->windowsFrom($own ?: $employee->department?->workHour);
    }

    /**
     * @return array{weekday_start: string, weekday_end: string, sunday_start: string, sunday_end: string}
     */
    private function windowsFrom(?object $source): array
    {
        return [
            'weekday_start' => $this->hours->clock($source?->weekday_start ?? null, '09:00'),
            'weekday_end' => $this->hours->clock($source?->weekday_end ?? null, '18:00'),
            'sunday_start' => $this->hours->clock($source?->sunday_start ?? null, '08:00'),
            'sunday_end' => $this->hours->clock($source?->sunday_end ?? null, '13:00'),
            'night_weekday_start' => $this->hours->clock($source?->night_weekday_start ?? null, '21:00'),
            'night_weekday_end' => $this->hours->clock($source?->night_weekday_end ?? null, '06:00'),
            'night_sunday_start' => $this->hours->clock($source?->night_sunday_start ?? null, '21:00'),
            'night_sunday_end' => $this->hours->clock($source?->night_sunday_end ?? null, '02:00'),
            'night_works_sunday' => (bool) ($source?->night_works_sunday ?? false),
            'night_sunday_break_start' => $this->hours->clockOrEmpty($source?->night_sunday_break_start ?? null),
            'night_sunday_break_end' => $this->hours->clockOrEmpty($source?->night_sunday_break_end ?? null),
            'weekday_break_start' => $this->hours->clockOrEmpty($source?->weekday_break_start ?? null),
            'weekday_break_end' => $this->hours->clockOrEmpty($source?->weekday_break_end ?? null),
            'sunday_break_start' => $this->hours->clockOrEmpty($source?->sunday_break_start ?? null),
            'sunday_break_end' => $this->hours->clockOrEmpty($source?->sunday_break_end ?? null),
            'night_weekday_break_start' => $this->hours->clockOrEmpty($source?->night_weekday_break_start ?? null),
            'night_weekday_break_end' => $this->hours->clockOrEmpty($source?->night_weekday_break_end ?? null),
        ];
    }
}
