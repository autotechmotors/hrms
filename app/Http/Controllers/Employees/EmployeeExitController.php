<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeExitRequest;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\EmploymentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeExitController extends Controller
{
    public function store(EmployeeExitRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);
        $status = EmploymentStatus::query()->findOrFail($request->integer('employment_status_id'));

        if ((int) $status->company_id !== (int) $employee->company_id || ! $status->is_exit) {
            throw ValidationException::withMessages([
                'employment_status_id' => 'Select an exit status configured for this company.',
            ]);
        }

        DB::transaction(function () use ($request, $employee, $status): void {
            $exit = $employee->latestExit;
            $payload = [
                'resignation_date' => $request->date('resignation_date'),
                'last_working_date' => $request->date('last_working_date'),
                'reason' => $request->string('reason')->toString(),
                'employment_status_id' => $status->id,
                'fnf_status' => $request->string('fnf_status')->toString(),
                'notes' => $request->input('notes'),
                'recorded_by' => $request->user()->id,
            ];

            if ($exit) {
                $exit->update($payload);
            } else {
                EmployeeExit::query()->create(['employee_id' => $employee->id] + $payload);
            }

            $employee->update(['employment_status_id' => $status->id]);
        });

        return back()->with('success', 'Exit record saved. Settlement amounts are calculated later in payroll.');
    }
}
