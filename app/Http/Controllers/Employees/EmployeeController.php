<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\Role;
use App\Services\AccessScope;
use App\Services\Devices\EmployeePush;
use App\Services\Employees\EmployeeExporter;
use App\Services\Employees\EmployeeImporter;
use App\Services\Employees\EmployeeRemover;
use App\Services\Employees\EmployeeService;
use App\Services\Operations\WorkHourResolver;
use App\Services\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    use GuardsCompany;

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly EmployeeImporter $importer,
        private readonly EmployeeExporter $exporter,
        private readonly EmployeeRemover $remover,
        private readonly WorkHourResolver $hours,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->filteredEmployees($request);
        $canEdit = $request->user()->hasPermission('employees.edit');
        $canDelete = $request->user()->hasPermission('employees.delete');
        $canBlock = $canEdit;
        $canSalary = $request->user()->hasPermission('salary.view');
        $credentialCounts = $this->credentialCounts($employees->getCollection());
        $credentialSlots = $this->credentialSlots($employees->getCollection());

        if ($request->ajax() || $request->boolean('partial')) {
            return view('employees._list', [
                'employees' => $employees,
                'canEdit' => $canEdit,
                'canDelete' => $canDelete,
                'canBlock' => $canBlock,
                'canSalary' => $canSalary,
                'credentialCounts' => $credentialCounts,
                'credentialSlots' => $credentialSlots,
            ]);
        }

        return view('employees.index', [
            'employees' => $employees,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
            'canBlock' => $canBlock,
            'canSalary' => $canSalary,
            'perPage' => $this->pageSize($request),
            'departments' => Department::query()->visibleTo($request->user())->orderBy('name')->get(),
            'statuses' => EmploymentStatus::query()->visibleTo($request->user())->orderBy('name')->get(),
            'credentialCounts' => $credentialCounts,
            'credentialSlots' => $credentialSlots,
            'terminals' => Device::query()->visibleTo($request->user())->orderBy('name')->get()->filter->isHikvision()->values(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('employees.form', $this->formData(null));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);
        $times = $this->validateTimes($request);
        $employee = $this->employees->create(
            $request->safe()->except(['photograph', 'remove_photo']),
            $request->user(),
            $request->file('photograph'),
        );
        $employee->refresh();
        if ($times) {
            $this->hours->saveForEmployee($employee, $times);
        }

        [$flash, $message] = $this->terminalMessage($employee, false);

        return redirect()->route('employees.show', $employee)->with($flash, $message);
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);
        $employee->load([
            'department.workHour', 'workHour', 'designation.employeeType', 'employeeType', 'role',
            'employmentStatus', 'manager.designation', 'manager.role', 'documents', 'events', 'exits.employmentStatus',
            'bankAccounts', 'activeBankAccount', 'latestExit.employmentStatus', 'customValues.field',
        ]);

        $attendanceRows = null;
        $user = request()->user();
        if ($user->hasPermission('attendance.view') && app(AccessScope::class)->canAccessEmployee($user, $employee)) {
            $stored = AttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '<=', now()->toDateString())
                ->orderByDesc('work_date')
                ->limit(31)
                ->get()
                ->keyBy(fn (AttendanceDay $day) => $day->work_date->toDateString());
            $today = now()->toDateString();
            $attendanceRows = collect([['date' => $today, 'day' => $stored->get($today)]]);
            foreach ($stored as $date => $day) {
                if ($date !== $today) {
                    $attendanceRows->push(['date' => $date, 'day' => $day]);
                }
            }
        }

        return view('employees.show', [
            'employee' => $employee,
            'customFields' => EmployeeCustomField::query()->where('company_id', $employee->company_id)->where('is_active', true)->orderBy('sort_order')->get(),
            'exitStatuses' => EmploymentStatus::query()->where('company_id', $employee->company_id)->where('is_exit', true)->where('is_active', true)->orderBy('name')->get(),
            'canViewAccount' => $user->hasPermission('bank_accounts.view'),
            'canManageBank' => $user->hasPermission('bank_accounts.manage'),
            'activeBank' => $employee->activeBankAccount,
            'attendanceRows' => $attendanceRows,
            'times' => $this->hours->editor($employee),
        ]);
    }

    public function updateTimes(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);
        $times = $this->validateTimes($request);
        if (! $times) {
            throw ValidationException::withMessages([
                'weekday_start' => 'Enter the Monday to Saturday start.',
            ]);
        }
        $this->hours->saveForEmployee($employee, $times);
        $employee->unsetRelation('workHour');
        $employee->load(['department.workHour', 'workHour']);

        return response()->json([
            'message' => 'Working times saved for '.$employee->full_name.'.',
            'html' => view('employees._times', [
                'employee' => $employee,
                'times' => $this->hours->editor($employee),
                'canEditTimes' => true,
                'standalone' => true,
            ])->render(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function terminalMessage(Employee $employee, bool $updated): array
    {
        $saved = $updated ? 'Employee updated.' : 'Employee saved.';
        try {
            $pushed = app(EmployeePush::class)->saveToTerminals($employee);
        } catch (RuntimeException $exception) {
            return ['warning', $saved.' The face terminal did not add them: '.$exception->getMessage()];
        }
        if ($pushed < 1) {
            return ['success', $saved];
        }
        $face = Device::query()
            ->where('company_id', $employee->company_id)
            ->get()
            ->contains(fn (Device $device) => $device->isHikvision());
        if ($face) {
            return ['success', $saved.' This person is on the face terminal. Add a face or finger from the employee list.'];
        }

        return ['success', $updated
            ? 'Employee updated. The terminal will refresh the name and photo on its next poll.'
            : 'Employee saved. The terminal will add the name and photo on its next poll.'];
    }

    public function collectCredential(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);
        set_time_limit(90);
        $data = $request->validate([
            'kind' => ['required', 'in:face,finger,card,password'],
            'password' => ['required_if:kind,password', 'nullable', 'regex:/^\d{4,8}$/'],
            'device_id' => ['required', 'integer'],
        ]);
        $device = null;
        if (! empty($data['device_id'])) {
            $device = Device::query()->visibleTo($request->user())->whereKey($data['device_id'])->first();
            if (! $device || ! $device->isHikvision() || (int) $device->company_id !== (int) $employee->company_id) {
                return response()->json(['message' => 'Choose a face terminal.'], 422);
            }
        }
        $result = app(EmployeePush::class)->collect($employee, $data['kind'], $data['password'] ?? null, $device);
        if ($result['devices'] === 0) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'counts' => $this->countsFor($employee),
            'slots' => $this->slotsFor($employee),
        ]);
    }

    public function destroyCredential(Employee $employee, EmployeeCredential $credential): JsonResponse
    {
        $this->authorize('update', $employee);
        abort_unless((int) $credential->employee_id === (int) $employee->id, 404);
        set_time_limit(60);
        try {
            $result = app(EmployeePush::class)->removeCredential($employee, $credential);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'counts' => $this->countsFor($employee),
            'slots' => $this->slotsFor($employee),
        ]);
    }

    public function credentialState(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return response()->json([
            'counts' => $this->countsFor($employee),
            'slots' => $this->slotsFor($employee),
        ]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.form', $this->formData($employee));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);
        $times = $this->validateTimes($request);
        $this->employees->update(
            $employee,
            $request->safe()->except(['photograph', 'remove_photo']),
            $request->user(),
            $request->file('photograph'),
        );
        $employee->refresh();
        if ($times) {
            $this->hours->saveForEmployee($employee, $times);
        }

        [$flash, $message] = $this->terminalMessage($employee, true);

        return redirect()->route('employees.show', $employee)->with($flash, $message);
    }

    public function toggleBlocked(Request $request, Employee $employee): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $employee);

        $employee->update([
            'is_blocked' => $request->boolean('is_blocked', ! $employee->is_blocked),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'message' => $employee->is_blocked ? 'Employee blocked.' : 'Employee unblocked.',
                'is_blocked' => $employee->is_blocked,
            ]);
        }

        return back()->with('success', $employee->is_blocked ? 'Employee blocked.' : 'Employee unblocked.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $employee);

        try {
            $this->remover->delete($employee);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'This employee cannot be deleted.';
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        } catch (QueryException) {
            $message = 'This employee could not be deleted because related records are still locked.';
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => 'Employee deleted. Related attendance, leave, pay, and login data were removed.']);
        }

        return redirect()->route('employees.index')->with('success', 'Employee deleted. Related attendance, leave, pay, and login data were removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('employees.export'), 403);

        return $this->exporter->download(Employee::query()->visibleTo($request->user()));
    }

    public function importForm(): View
    {
        abort_unless(request()->user()->hasPermission('employees.import'), 403);

        return view('employees.import', ['companies' => $this->visibleCompanies()]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('employees.import'), 403);
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);
        $this->guardCompany((int) $data['company_id']);
        $result = $this->importer->import($request->file('file'), (int) $data['company_id'], $request->user());

        if ($result['errors'] !== []) {
            return back()->with('error', implode(' ', $result['errors']));
        }

        return redirect()->route('employees.index')->with('success', $result['created'].' employees imported.');
    }

    public function template()
    {
        abort_unless(request()->user()->hasPermission('employees.import'), 403);

        return response()->download($this->importer->template(), 'employee-import-template.xlsx');
    }

    public function photo(Employee $employee)
    {
        $this->authorize('view', $employee);
        abort_unless($employee->photograph_path && Storage::disk('local')->exists($employee->photograph_path), 404);

        return Storage::disk('local')->response($employee->photograph_path);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, int>>
     */
    private function credentialCounts($employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $counts = [];
        $rows = EmployeeCredential::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, kind, COUNT(*) as total')
            ->groupBy('employee_id', 'kind')
            ->get();
        foreach ($rows as $row) {
            $counts[(int) $row->employee_id][(string) $row->kind] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function countsFor(Employee $employee): array
    {
        $counts = ['password' => 0, 'card' => 0, 'finger' => 0, 'face' => 0];
        $rows = EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->selectRaw('kind, COUNT(*) as total')
            ->groupBy('kind')
            ->get();
        foreach ($rows as $row) {
            if (array_key_exists((string) $row->kind, $counts)) {
                $counts[(string) $row->kind] = (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, list<array{id: int, slot: string}>>
     */
    private function slotsFor(Employee $employee): array
    {
        $slots = ['face' => [], 'finger' => []];
        $rows = EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->whereIn('kind', ['face', 'finger'])
            ->orderBy('slot')
            ->get(['id', 'kind', 'slot']);
        foreach ($rows as $row) {
            $slots[(string) $row->kind][] = ['id' => (int) $row->id, 'slot' => (string) $row->slot];
        }

        return $slots;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, list<array{id: int, slot: string}>>>
     */
    private function credentialSlots($employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $slots = [];
        $rows = EmployeeCredential::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereIn('kind', ['face', 'finger'])
            ->orderBy('slot')
            ->get(['id', 'employee_id', 'kind', 'slot']);
        foreach ($rows as $row) {
            $slots[(int) $row->employee_id][(string) $row->kind][] = [
                'id' => (int) $row->id,
                'slot' => (string) $row->slot,
            ];
        }

        return $slots;
    }

    private function pageSize(Request $request): int
    {
        $size = (int) $request->query('per_page', 12);

        return in_array($size, [12, 24, 48], true) ? $size : 12;
    }

    private function filteredEmployees(Request $request): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['department', 'designation', 'employmentStatus', 'workHour', 'shiftSpans'])
            ->withCount([
                'documents',
                'activeBankAccount as active_bank_accounts_count',
            ])
            ->visibleTo($request->user())
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('employment_status_id'), fn ($query) => $query->where('employment_status_id', $request->integer('employment_status_id')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('employee_code', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('employee_code')
            ->paginate($this->pageSize($request))
            ->onEachSide(1)
            ->appends($request->except(['page', 'partial']));
    }

    /**
     * @return array{weekday_start: string, weekday_end: string, sunday_start?: string, sunday_end?: string, works_sunday: bool}|null
     */
    private function validateTimes(Request $request): ?array
    {
        if (! $request->exists('weekday_start')) {
            return null;
        }

        $works = $request->boolean('works_sunday');
        $rules = [
            'weekday_start' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'weekday_end' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ];
        if ($works) {
            $rules['sunday_start'] = ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'];
            $rules['sunday_end'] = ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'];
        }
        $data = $request->validate($rules, [
            'weekday_start.required' => 'Enter the Monday to Saturday start.',
            'weekday_end.required' => 'Enter the Monday to Saturday end.',
            'sunday_start.required' => 'Enter the Sunday start.',
            'sunday_end.required' => 'Enter the Sunday end.',
        ]);
        $weekStart = $this->hours->clock($data['weekday_start'], '09:00');
        $weekEnd = $this->hours->clock($data['weekday_end'], '18:00');
        if ($this->hours->span($weekStart, $weekEnd) < 30) {
            throw ValidationException::withMessages([
                'weekday_end' => 'Monday to Saturday must cover at least 30 minutes.',
            ]);
        }
        $kind = $request->input('shift_kind');
        $night = $kind === 'night';
        $weekBreak = $this->checkedBreak($request, 'weekday_break_start', 'weekday_break_end', $weekStart, $weekEnd, 'weekday_break_end', 'Monday to Saturday break');
        $sunBreak = ['start' => null, 'end' => null];
        if ($works) {
            $sunStart = $this->hours->clock($data['sunday_start'], '08:00');
            $sunEnd = $this->hours->clock($data['sunday_end'], '13:00');
            if ($this->hours->span($sunStart, $sunEnd) < 30) {
                throw ValidationException::withMessages([
                    'sunday_end' => 'Sunday must cover at least 30 minutes.',
                ]);
            }
            $sunBreak = $this->checkedBreak($request, 'sunday_break_start', 'sunday_break_end', $sunStart, $sunEnd, 'sunday_break_end', $night ? 'Sunday night break' : 'Sunday break');
            if ($night && ($sunBreak['start'] === null || $sunBreak['end'] === null)) {
                throw ValidationException::withMessages([
                    'sunday_break_end' => 'Enter the Sunday night break.',
                ]);
            }
        }

        return [
            'weekday_start' => $data['weekday_start'],
            'weekday_end' => $data['weekday_end'],
            'sunday_start' => $data['sunday_start'] ?? null,
            'sunday_end' => $data['sunday_end'] ?? null,
            'weekday_break_start' => $weekBreak['start'],
            'weekday_break_end' => $weekBreak['end'],
            'sunday_break_start' => $sunBreak['start'],
            'sunday_break_end' => $sunBreak['end'],
            'works_sunday' => $works,
            'shift_kind' => $night ? 'night' : ($kind === 'day' ? 'day' : null),
        ];
    }

    /**
     * @return array{start: ?string, end: ?string}
     */
    private function checkedBreak(Request $request, string $startKey, string $endKey, string $windowStart, string $windowEnd, string $field, string $label): array
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

        return $pair;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Employee $employee): array
    {
        $user = request()->user();
        $employee?->load(['customValues', 'role', 'user.roles']);
        $companyId = $employee?->company_id ?? app(Workspace::class)->company($user)?->id;

        $catalog = Employee::query()
            ->visibleTo($user)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
            ->with(['designation', 'role', 'user.roles'])
            ->orderBy('first_name');

        $managers = (clone $catalog)
            ->where(function ($query) use ($employee): void {
                $query->canManagePeople();
                if ($employee?->reporting_manager_id) {
                    $query->orWhere($query->getModel()->getQualifiedKeyName(), $employee->reporting_manager_id);
                }
            })
            ->get();

        return [
            'employee' => $employee,
            'needsReportingManager' => (bool) $employee?->needsReportingManager(),
            'departments' => Department::query()->visibleTo($user)->when($companyId, fn ($query) => $query->where('company_id', $companyId))->orderBy('name')->get(),
            'designations' => Designation::query()->with('employeeType')->visibleTo($user)->when($companyId, fn ($query) => $query->where('company_id', $companyId))->orderBy('name')->get(),
            'types' => EmployeeType::query()->visibleTo($user)->when($companyId, fn ($query) => $query->where('company_id', $companyId))->orderBy('name')->get(),
            'statuses' => EmploymentStatus::query()->visibleTo($user)->when($companyId, fn ($query) => $query->where('company_id', $companyId))->orderBy('name')->get(),
            'roles' => Role::query()
                ->where(function ($query) use ($employee): void {
                    $query->where('is_active', true);
                    if ($employee?->role_id) {
                        $query->orWhere($query->getModel()->getQualifiedKeyName(), $employee->role_id);
                    }
                })
                ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', 'super-admin'))
                ->orderBy('name')
                ->get(),
            'managers' => $managers,
            'customFields' => EmployeeCustomField::query()
                ->visibleTo($user)
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'times' => $employee
                ? $this->hours->editor($employee->loadMissing(['workHour', 'department.workHour']))
                : [
                    'weekday_start' => '09:00',
                    'weekday_end' => '18:00',
                    'sunday_start' => '08:00',
                    'sunday_end' => '13:00',
                    'works_sunday' => true,
                    'shift_kind' => 'day',
                    'day_start' => '09:00',
                    'day_end' => '18:00',
                    'night_start' => '21:00',
                    'night_end' => '06:00',
                    'day_sunday_start' => '08:00',
                    'day_sunday_end' => '13:00',
                    'night_sunday_start' => '21:00',
                    'night_sunday_end' => '02:00',
                    'weekday_break_start' => '',
                    'weekday_break_end' => '',
                    'sunday_break_start' => '',
                    'sunday_break_end' => '',
                    'day_break_start' => '',
                    'day_break_end' => '',
                    'night_break_start' => '',
                    'night_break_end' => '',
                    'day_sunday_break_start' => '',
                    'day_sunday_break_end' => '',
                    'night_sunday_break_start' => '',
                    'night_sunday_break_end' => '',
                    'night_works_sunday' => false,
                    'department_name' => 'this department',
                    'department_night_sunday_start' => '21:00',
                    'department_night_sunday_end' => '02:00',
                    'department_night_sunday_break_start' => '',
                    'department_night_sunday_break_end' => '',
                ],
            'terminals' => $employee
                ? Device::query()
                    ->where('company_id', $employee->company_id)
                    ->where(function ($query) use ($employee): void {
                        $query->whereHas('users', fn ($users) => $users->where('employee_id', $employee->id))
                            ->orWhereHas('terminalUsers', fn ($people) => $people->where('employee_id', $employee->id));
                    })
                    ->orderBy('name')
                    ->get()
                : collect(),
        ];
    }
}
