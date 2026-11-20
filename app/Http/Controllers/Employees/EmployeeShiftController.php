<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Operations\ShiftSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeShiftController extends Controller
{
    public function __construct(private readonly ShiftSchedule $schedule) {}

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return response()->json([
            'html' => view('employees._shift_month', [
                'employee' => $employee,
                'year' => max(1990, min(2100, $year)),
                'month' => max(1, min(12, $month)),
            ])->render(),
        ]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        abort_unless($this->schedule->canAssign($request->user(), $employee), 403);

        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        if ($year < 1990 || $year > 2100 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'month' => 'Choose a real month.',
            ]);
        }

        $continuous = $request->input('continuous');
        if (! in_array($continuous, ['day', 'night'], true)) {
            $continuous = null;
        }

        $ranges = $request->input('ranges', []);
        $this->schedule->saveMonth($employee, $year, $month, $continuous, is_array($ranges) ? $ranges : []);

        return response()->json([
            'message' => 'Shift saved. Each date uses that clock for in, out, and cutoff.',
            'html' => view('employees._shift_month', [
                'employee' => $employee->fresh(),
                'year' => $year,
                'month' => $month,
            ])->render(),
        ]);
    }
}
