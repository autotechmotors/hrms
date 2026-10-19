<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\EmployeeSalary;
use App\Models\SalaryHead;
use App\Models\SalaryStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalaryController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $companyId = $this->companyId();
        $q = trim((string) $request->input('q', ''));

        $heads = SalaryHead::query()
            ->visibleTo($request->user())
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $structures = SalaryStructure::query()
            ->visibleTo($request->user())
            ->where('company_id', $companyId)
            ->with('lines.head')
            ->orderBy('name')
            ->get();

        $assignments = EmployeeSalary::query()
            ->whereIn('employee_id', $this->activeEmployees()->pluck('id'))
            ->with(['employee.department', 'structure'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->whereHas('employee', function ($employee) use ($like) {
                    $employee->where('employee_code', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
            })
            ->latest('effective_from')
            ->limit(50)
            ->get();

        return view('operations.salary.index', [
            'heads' => $heads,
            'structures' => $structures,
            'assignments' => $assignments,
            'employees' => $this->activeEmployees()->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']),
            'stats' => [
                'heads' => $heads->count(),
                'earnings' => $heads->where('category', 'earning')->count(),
                'deductions' => $heads->where('category', 'deduction')->count(),
                'structures' => $structures->count(),
                'assignments' => $assignments->count(),
            ],
            'filters' => ['q' => $q],
            'canEdit' => $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function storeHead(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $companyId = $this->companyId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('salary_heads', 'code')->where('company_id', $companyId)],
            'category' => ['required', Rule::in(['earning', 'deduction'])],
            'method' => ['required', Rule::in(['fixed', 'percent_gross', 'percent_basic'])],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        SalaryHead::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'category' => $data['category'],
            'method' => $data['method'],
            'value' => $data['value'],
            'sort_order' => (int) SalaryHead::query()->where('company_id', $companyId)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('salary.index')->with('success', 'Salary head added. Use code BASIC when a percent should be calculated from basic.');
    }

    public function storeStructure(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $companyId = $this->companyId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('salary_structures', 'code')->where('company_id', $companyId)],
            'head_ids' => ['required', 'array', 'min:1'],
            'head_ids.*' => ['integer'],
        ]);
        $heads = SalaryHead::query()->visibleTo($request->user())->where('company_id', $companyId)->whereIn('id', $data['head_ids'])->get();
        abort_if($heads->isEmpty(), 422);

        $structure = SalaryStructure::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'is_active' => true,
        ]);
        foreach ($heads as $head) {
            $structure->lines()->create(['salary_head_id' => $head->id, 'value' => null]);
        }

        return redirect()->route('salary.index')->with('success', 'Salary structure saved.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'salary_structure_id' => ['required', 'integer'],
            'gross_monthly' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        SalaryStructure::query()->visibleTo($request->user())->where('company_id', $this->companyId())->findOrFail($data['salary_structure_id']);

        EmployeeSalary::query()->create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $data['salary_structure_id'],
            'gross_monthly' => $data['gross_monthly'],
            'effective_from' => $data['effective_from'],
        ]);

        return redirect()->route('salary.index', array_filter(['q' => $request->input('q')]))
            ->with('success', 'Monthly gross assigned. Payroll uses the row effective on the pay month.');
    }
}
