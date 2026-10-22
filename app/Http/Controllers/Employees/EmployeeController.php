<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\Location;
use App\Services\Employees\EmployeeExporter;
use App\Services\Employees\EmployeeImporter;
use App\Services\Employees\EmployeeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    use GuardsCompany;

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly EmployeeImporter $importer,
        private readonly EmployeeExporter $exporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->filteredEmployees($request);
        $canManage = $request->user()->isSuperAdmin();
        $canBlock = $request->user()->hasPermission('employees.edit');
        $credentialCounts = $this->credentialCounts($employees->getCollection());

        if ($request->ajax() || $request->boolean('partial')) {
            return view('employees._list', [
                'employees' => $employees,
                'canManage' => $canManage,
                'canBlock' => $canBlock,
                'credentialCounts' => $credentialCounts,
            ]);
        }

        return view('employees.index', [
            'employees' => $employees,
            'canManage' => $canManage,
            'canBlock' => $canBlock,
            'companies' => $this->visibleCompanies(),
            'branches' => Branch::query()->visibleTo($request->user())->orderBy('name')->get(),
            'departments' => Department::query()->visibleTo($request->user())->orderBy('name')->get(),
            'statuses' => EmploymentStatus::query()->visibleTo($request->user())->orderBy('name')->get(),
            'credentialCounts' => $credentialCounts,
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
        $employee = $this->employees->create(
            $request->safe()->except(['photograph', 'remove_photo']),
            $request->user(),
            $request->file('photograph'),
        );
        $employee->refresh();
        $pushed = app(\App\Services\Devices\EmployeePush::class)->sync($employee);

        return redirect()->route('employees.show', $employee)->with('success', $pushed > 0
            ? 'Employee saved. The terminal will add the name and photo on its next poll.'
            : 'Employee saved.');
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);
        $employee->load([
            'company', 'branch', 'location', 'department', 'designation.employeeType', 'employeeType',
            'employmentStatus', 'manager', 'documents', 'events', 'exits.employmentStatus',
            'bankAccounts', 'activeBankAccount', 'latestExit.employmentStatus', 'customValues.field',
        ]);

        return view('employees.show', [
            'employee' => $employee,
            'customFields' => EmployeeCustomField::query()->where('company_id', $employee->company_id)->where('is_active', true)->orderBy('sort_order')->get(),
            'exitStatuses' => EmploymentStatus::query()->where('company_id', $employee->company_id)->where('is_exit', true)->where('is_active', true)->orderBy('name')->get(),
            'canViewAccount' => request()->user()->hasPermission('bank_accounts.view'),
            'canManageBank' => request()->user()->hasPermission('bank_accounts.manage'),
            'activeBank' => $employee->activeBankAccount,
        ]);
    }

    public function collectCredential(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);
        $data = $request->validate([
            'kind' => ['required', 'in:face,finger,card,password'],
            'password' => ['required_if:kind,password', 'nullable', 'regex:/^\d{4,8}$/'],
        ]);
        $result = app(\App\Services\Devices\EmployeePush::class)->collect($employee, $data['kind'], $data['password'] ?? null);
        if ($result['devices'] === 0) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'counts' => $this->countsFor($employee),
        ]);
    }

    public function credentialState(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);
        \App\Models\Device::query()->where('company_id', $employee->company_id)->orderBy('id')->each(function ($device): void {
            try {
                $push = app(\App\Services\Devices\DahuaPush::class);
                $push->importInbox($device);
                $push->continueCollect($device);
            } catch (\Throwable) {
            }
        });

        return response()->json(['counts' => $this->countsFor($employee)]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.form', $this->formData($employee));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);
        $this->employees->update(
            $employee,
            $request->safe()->except(['photograph', 'remove_photo']),
            $request->user(),
            $request->file('photograph'),
        );
        $employee->refresh();
        $pushed = app(\App\Services\Devices\EmployeePush::class)->sync($employee);
        $message = $pushed > 0
            ? 'Employee updated. The terminal will refresh the name and photo on its next poll.'
            : 'Employee updated.';

        return redirect()->route('employees.show', $employee)->with('success', $message);
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

        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $push = app(\App\Services\Devices\EmployeePush::class);
        $linked = $push->linked($employee);
        $userId = trim((string) $employee->employee_code);

        try {
            DB::transaction(function () use ($employee, $linked, $userId): void {
                $kept = [];
                foreach ($linked as $device) {
                    $row = DeviceTerminalUser::query()->updateOrCreate(
                        ['device_id' => $device->id, 'user_id' => $userId],
                        ['name' => $employee->full_name, 'employee_id' => $employee->id],
                    );
                    $kept[] = $row->id;
                }
                if ($employee->photograph_path) {
                    Storage::disk('local')->delete($employee->photograph_path);
                }
                $employee->delete();
                if ($kept !== []) {
                    DeviceTerminalUser::query()->whereIn('id', $kept)->update(['dismissed_at' => now()]);
                }
            });
            $push->drop($linked, $userId);
        } catch (QueryException) {
            if ($request->ajax()) {
                return response()->json(['message' => 'This employee has history or documents and cannot be deleted.'], 422);
            }

            return back()->with('error', 'This employee has history or documents and cannot be deleted.');
        }

        if ($request->ajax()) {
            return response()->json(['message' => 'Employee deleted.']);
        }

        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
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
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @return array<int, array<string, int>>
     */
    private function credentialCounts($employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $counts = [];
        $rows = \App\Models\EmployeeCredential::query()
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
        $rows = \App\Models\EmployeeCredential::query()
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

    private function filteredEmployees(Request $request): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['company', 'branch', 'department', 'designation', 'employmentStatus'])
            ->withCount([
                'documents',
                'activeBankAccount as active_bank_accounts_count',
            ])
            ->visibleTo($request->user())
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->integer('company_id')))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
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
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Employee $employee): array
    {
        $user = request()->user();
        $employee?->load('customValues');

        return [
            'employee' => $employee,
            'companies' => $this->visibleCompanies(),
            'branches' => Branch::query()->visibleTo($user)->orderBy('name')->get(),
            'locations' => Location::query()->visibleTo($user)->orderBy('name')->get(),
            'departments' => Department::query()->visibleTo($user)->orderBy('name')->get(),
            'designations' => Designation::query()->with('employeeType')->visibleTo($user)->orderBy('name')->get(),
            'types' => EmployeeType::query()->visibleTo($user)->orderBy('name')->get(),
            'statuses' => EmploymentStatus::query()->visibleTo($user)->orderBy('name')->get(),
            'managers' => Employee::query()->visibleTo($user)->when($employee, fn ($query) => $query->whereKeyNot($employee->id))->orderBy('first_name')->get(),
            'customFields' => EmployeeCustomField::query()
                ->visibleTo($user)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'terminals' => $employee
                ? \App\Models\Device::query()
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
