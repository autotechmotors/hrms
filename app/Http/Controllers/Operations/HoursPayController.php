<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Department;
use App\Models\DepartmentWorkHour;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeWorkHour;
use App\Models\SalaryStructure;
use App\Services\Operations\WorkHourResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'people' => (int) ($counts[$department->id] ?? 0),
                    'hours' => $this->hours->label($value !== null ? (float) $value : null),
                    'value' => $value !== null ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') : '',
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

        $data = $request->validate([
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:24'],
        ], [
            'hours_per_day.required' => 'Enter how many hours this department works in a day.',
            'hours_per_day.min' => 'Hours must be at least half an hour.',
            'hours_per_day.max' => 'Hours cannot be more than 24.',
        ]);

        DepartmentWorkHour::query()->updateOrCreate(
            ['department_id' => $department->id],
            ['hours_per_day' => $data['hours_per_day']]
        );

        $hours = (float) $data['hours_per_day'];

        return response()->json([
            'message' => $department->name.' now works '.$this->hours->label($hours).' a day.',
            'hours' => $this->hours->label($hours),
            'value' => rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.'),
        ]);
    }

    public function storeEmployeeHours(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeAny('salary.manage');
        $employee = $this->visibleEmployee($request, $employee);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['department', 'own'])],
            'hours_per_day' => ['nullable', 'numeric', 'min:0.5', 'max:24', 'required_if:mode,own'],
        ], [
            'hours_per_day.required_if' => 'Enter this person’s own hours.',
            'hours_per_day.min' => 'Hours must be at least half an hour.',
            'hours_per_day.max' => 'Hours cannot be more than 24.',
        ]);

        EmployeeWorkHour::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'uses_department' => $data['mode'] === 'department',
                'hours_per_day' => $data['mode'] === 'own' ? $data['hours_per_day'] : null,
            ]
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
        $data = $request->validate([
            'salary_structure_id' => ['required', 'integer'],
            'gross_monthly' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ], [
            'salary_structure_id.required' => 'Choose a salary structure.',
            'gross_monthly.required' => 'Enter the monthly salary.',
            'effective_from.required' => 'Choose the date this salary starts.',
        ]);

        SalaryStructure::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->findOrFail($data['salary_structure_id']);

        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $data['salary_structure_id'],
            'gross_monthly' => $data['gross_monthly'],
            'effective_from' => $data['effective_from'],
        ]);

        return response()->json([
            'message' => 'Monthly salary saved. Payroll uses the amount that is already effective in the pay month.',
            'html' => view('operations.hours-pay._detail', $this->detail($request, $employee))->render(),
        ]);
    }

    /**
     * @param  array{q: string, department_id: string}  $filters
     */
    private function people(Request $request, array $filters)
    {
        return $this->peopleQuery()
            ->with(['department.workHour', 'workHour', 'salaries' => function ($query): void {
                $query->whereDate('effective_from', '<=', today())->with('structure')->orderByDesc('effective_from');
            }])
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
            ->paginate(12)
            ->appends($request->except(['page', 'partial']))
            ->through(function (Employee $employee) {
                $resolved = $this->hours->resolve($employee->department?->workHour, $employee->workHour);
                $salary = $employee->salaries->first();

                return [
                    'url' => route('hours-pay.show', $employee),
                    'name' => $employee->full_name,
                    'code' => $employee->employee_code,
                    'department' => $employee->department?->name ?? 'No department',
                    'hours' => $this->hours->label($resolved['hours']),
                    'source' => $resolved['source'] === 'unset' ? 'Not chosen yet' : $this->hours->sourceLabel($resolved['source']),
                    'salary' => $salary ? '₹'.number_format((float) $salary->gross_monthly, 2) : 'Not set',
                    'salaryNote' => $salary ? 'a month' : 'No salary yet',
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
            'structures' => SalaryStructure::query()
                ->visibleTo($request->user())
                ->where('company_id', $this->companyId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'canEdit' => $request->user()->hasPermission('salary.manage'),
            'mode' => $employee->workHour && ! $employee->workHour->uses_department ? 'own' : 'department',
            'ownHours' => $employee->workHour && ! $employee->workHour->uses_department
                ? rtrim(rtrim(number_format((float) $employee->workHour->hours_per_day, 2, '.', ''), '0'), '.')
                : '',
        ];
    }
}
