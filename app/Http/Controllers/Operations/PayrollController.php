<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\Operations\PayrollRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();

        $runs = PayrollRun::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->withCount('items')
            ->withSum('items', 'gross')
            ->withSum('items', 'deductions')
            ->withSum('items', 'net')
            ->latest('period_year')
            ->latest('period_month')
            ->get();

        return view('operations.payroll.index', [
            'runs' => $runs,
            'stats' => [
                'total' => $runs->count(),
                'draft' => $runs->where('status', 'draft')->count(),
                'computed' => $runs->where('status', 'computed')->count(),
                'approved' => $runs->where('status', 'approved')->count(),
            ],
            'canProcess' => $request->user()->hasPermission('payroll.process') || $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePayroll();
        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $run = PayrollRun::query()->firstOrCreate(
            [
                'company_id' => $this->companyId(),
                'period_year' => $data['period_year'],
                'period_month' => $data['period_month'],
            ],
            ['status' => 'draft', 'working_days' => 0],
        );

        return redirect()->route('payroll.show', $run)->with('success', 'Payroll period opened.');
    }

    public function show(Request $request, PayrollRun $payroll): View
    {
        $this->authorizeView();
        abort_unless((int) $payroll->company_id === $this->companyId(), 404);
        $payroll->load('items.employee');
        $paidIds = $payroll->items->pluck('employee_id');
        $missing = Employee::query()
            ->visibleTo($request->user())
            ->where('company_id', $payroll->company_id)
            ->whereHas('employmentStatus', fn ($query) => $query->where('is_exit', false))
            ->whereNotIn('id', $paidIds)
            ->orderBy('first_name')
            ->get();

        return view('operations.payroll.show', [
            'run' => $payroll,
            'missing' => $missing,
            'totals' => [
                'employees' => $payroll->items->count(),
                'gross' => (float) $payroll->items->sum('gross'),
                'deductions' => (float) $payroll->items->sum('deductions'),
                'net' => (float) $payroll->items->sum('net'),
            ],
            'canProcess' => $request->user()->hasPermission('payroll.process') || $request->user()->hasPermission('employees.edit'),
            'canApprove' => $request->user()->hasPermission('payroll.approve') || $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function compute(PayrollRun $payroll, PayrollRunner $runner): RedirectResponse
    {
        $this->authorizePayroll();
        abort_unless((int) $payroll->company_id === $this->companyId(), 404);
        try {
            $runner->compute($payroll);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return back()->with('success', 'Payroll calculated from attendance, unpaid leave, salary heads, and advance EMI. Nothing is paid yet.');
    }

    public function approve(PayrollRun $payroll, PayrollRunner $runner): RedirectResponse
    {
        $this->authorizePayrollApprove();
        abort_unless((int) $payroll->company_id === $this->companyId(), 404);
        try {
            $runner->approve($payroll);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return back()->with('success', 'Payroll approved. Advance balances were reduced by this month’s EMI.');
    }
}
