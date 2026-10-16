<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeBankAccountRequest;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;

class EmployeeBankAccountController extends Controller
{
    public function store(EmployeeBankAccountRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('view', $employee);
        $number = preg_replace('/\s+/', '', $request->string('account_number')->toString()) ?? '';

        DB::transaction(function () use ($request, $employee, $number): void {
            EmployeeBankAccount::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            EmployeeBankAccount::query()->create([
                'employee_id' => $employee->id,
                'beneficiary_name' => $request->string('beneficiary_name')->toString(),
                'bank_name' => $request->string('bank_name')->toString(),
                'account_number' => $number,
                'account_last4' => substr($number, -4),
                'ifsc' => strtoupper($request->string('ifsc')->toString()),
                'branch_name' => $request->input('branch_name'),
                'effective_from' => $request->date('effective_from'),
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Bank account saved. The previous account is kept as history.');
    }
}
