<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeType;
use App\Models\EmploymentStatus;
use App\Models\Location;
use App\Services\Employees\EmployeeExporter;
use App\Services\Employees\EmployeeImporter;
use App\Services\Employees\EmployeeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->with(['company', 'branch', 'department', 'designation', 'employmentStatus'])
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

        return view('employees.index', [
            'employees' => $employees,
            'companies' => $this->visibleCompanies(),
            'branches' => Branch::query()->visibleTo($request->user())->orderBy('name')->get(),
            'departments' => Department::query()->visibleTo($request->user())->orderBy('name')->get(),
            'statuses' => EmploymentStatus::query()->visibleTo($request->user())->orderBy('name')->get(),
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

        return redirect()->route('employees.show', $employee)->with('success', 'Employee saved.');
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);
        $employee->load([
            'company', 'branch', 'location', 'department', 'designation.employeeType', 'employeeType',
            'employmentStatus', 'manager', 'documents', 'events', 'exits.employmentStatus',
            'bankAccounts', 'customValues.field',
        ]);

        return view('employees.show', [
            'employee' => $employee,
            'customFields' => EmployeeCustomField::query()->where('company_id', $employee->company_id)->where('is_active', true)->orderBy('sort_order')->get(),
            'exitStatuses' => EmploymentStatus::query()->where('company_id', $employee->company_id)->where('is_exit', true)->where('is_active', true)->orderBy('name')->get(),
            'canViewAccount' => request()->user()->hasPermission('bank_accounts.view'),
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
        $this->employees->update(
            $employee,
            $request->safe()->except(['photograph', 'remove_photo']),
            $request->user(),
            $request->file('photograph'),
        );

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);
        try {
            if ($employee->photograph_path) {
                Storage::disk('local')->delete($employee->photograph_path);
            }
            $employee->delete();
        } catch (QueryException) {
            return back()->with('error', 'This employee has history or documents and cannot be deleted.');
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
        ];
    }
}
