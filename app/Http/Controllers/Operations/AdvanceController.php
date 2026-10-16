<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Advance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvanceController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $employeeIds = $this->activeEmployees()->pluck('id');

        return view('operations.advances.index', [
            'advances' => Advance::query()->whereIn('employee_id', $employeeIds)->with('employee')->latest()->paginate(20),
            'employees' => $this->activeEmployees()->get(),
            'canEdit' => $request->user()->hasPermission('employees.edit') || $request->user()->hasPermission('payroll.process'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('employees.edit') || $request->user()->hasPermission('payroll.process'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:1'],
            'emi' => ['required', 'numeric', 'min:0'],
            'starts_on' => ['required', 'date'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        Advance::query()->create([
            'employee_id' => $employee->id,
            'title' => $data['title'],
            'amount' => $data['amount'],
            'emi' => $data['emi'],
            'balance' => $data['amount'],
            'starts_on' => $data['starts_on'],
            'status' => 'active',
        ]);

        return back()->with('success', 'Advance recorded. EMI is deducted only when that month’s payroll is approved.');
    }

    public function close(Advance $advance): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('employees.edit') || request()->user()->hasPermission('payroll.process'), 403);
        $advance->load('employee');
        abort_unless($advance->employee && (int) $advance->employee->company_id === $this->companyId(), 404);
        $advance->update(['status' => 'closed']);

        return back()->with('success', 'Advance closed. Remaining balance will not be deducted.');
    }
}
