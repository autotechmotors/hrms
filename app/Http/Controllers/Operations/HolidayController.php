<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Employee;
use App\Models\EmployeeHoliday;
use App\Models\Holiday;
use App\Services\Operations\AttendanceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HolidayController extends Controller
{
    use UsesWorkspace;

    public function __construct(private readonly AttendanceCalculator $attendance) {}

    public function index(Request $request): View|RedirectResponse|Response
    {
        $this->authorizeAny('holidays.view');
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $clampedYear = max(1990, min(2100, $year));
        $clampedMonth = max(1, min(12, $month));
        if ($clampedYear !== $year || ($request->filled('month') && $clampedMonth !== $month)) {
            return redirect()->route('holidays.index', ['year' => $clampedYear, 'month' => $clampedMonth]);
        }

        $data = $this->pageData($request, $clampedYear, $clampedMonth);
        if ($request->boolean('partial') && $request->ajax()) {
            return response(view('operations.holidays._body', $data)->render());
        }

        return view('operations.holidays.index', $data);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('holidays.manage');
        $companyId = $this->companyId();
        $data = $request->validate([
            'holiday_date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['public', 'cutoff'])],
        ]);

        $holiday = Holiday::query()->create([
            'company_id' => $companyId,
            'holiday_date' => $data['holiday_date'],
            'name' => $data['name'],
            'kind' => $data['kind'],
        ]);

        $date = Carbon::parse($holiday->holiday_date);
        $this->refreshCompany($companyId, $date);

        $year = $date->year;
        $message = $holiday->kind === 'cutoff'
            ? 'Holiday added. This day is cut from everyone’s salary.'
            : 'Holiday added. This is a paid day for everyone.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'year' => $year,
                'month' => $date->month,
            ]);
        }

        return redirect()
            ->route('holidays.index', ['year' => $year, 'month' => $date->month])
            ->with('success', $message);
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('holidays.manage');
        abort_unless((int) $holiday->company_id === $this->companyId(), 404);
        $year = $holiday->holiday_date?->year ?? now()->year;
        $date = Carbon::parse($holiday->holiday_date);
        $companyId = (int) $holiday->company_id;
        $holiday->delete();
        $this->refreshCompany($companyId, $date);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Holiday removed. That day is a working day again.']);
        }

        return redirect()
            ->route('holidays.index', ['year' => $year, 'month' => $date->month])
            ->with('success', 'Holiday removed.');
    }

    public function storeEmployee(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('holidays.manage');
        $companyId = $this->companyId();
        $range = $request->filled('starts_on') || $request->filled('ends_on');
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'holiday_date' => [Rule::requiredIf(! $range), 'nullable', 'date'],
            'starts_on' => [Rule::requiredIf($range), 'nullable', 'date'],
            'ends_on' => [Rule::requiredIf($range), 'nullable', 'date', 'after_or_equal:starts_on'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $employee = Employee::query()
            ->visibleTo($request->user())
            ->where('company_id', $companyId)
            ->whereKey((int) $data['employee_id'])
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages(['employee_id' => 'Choose a person you can see.']);
        }

        $start = Carbon::parse($range ? $data['starts_on'] : $data['holiday_date'])->startOfDay();
        $end = Carbon::parse($range ? $data['ends_on'] : $data['holiday_date'])->startOfDay();
        $span = (int) round($start->diffInDays($end)) + 1;
        if ($span > 366) {
            throw ValidationException::withMessages(['ends_on' => 'Choose 366 days or fewer.']);
        }

        $taken = EmployeeHoliday::query()
            ->where('employee_id', $employee->id)
            ->whereDate('holiday_date', '>=', $start->toDateString())
            ->whereDate('holiday_date', '<=', $end->toDateString())
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                $range ? 'starts_on' : 'holiday_date' => 'This person already has a paid day inside that range.',
            ]);
        }

        $dates = [];
        DB::transaction(function () use ($employee, $start, $end, $data, &$dates) {
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                EmployeeHoliday::query()->create([
                    'employee_id' => $employee->id,
                    'holiday_date' => $cursor->toDateString(),
                    'name' => $data['name'],
                ]);
                $dates[] = $cursor->copy();
                $cursor->addDay();
            }
        });
        foreach ($dates as $date) {
            $this->attendance->recalculate($employee, $date);
        }

        $message = count($dates) === 1
            ? $employee->full_name.' is paid on that day. Everyone else still works.'
            : $employee->full_name.' is paid for '.count($dates).' days. Everyone else still works.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'year' => $start->year,
                'month' => $start->month,
            ]);
        }

        return redirect()
            ->route('holidays.index', ['year' => $start->year, 'month' => $start->month])
            ->with('success', 'Paid day saved for '.$employee->full_name.'.');
    }

    public function destroyEmployee(Request $request, EmployeeHoliday $employeeHoliday): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('holidays.manage');
        $employee = Employee::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->whereKey($employeeHoliday->employee_id)
            ->first();
        abort_unless($employee !== null, 404);

        $date = Carbon::parse($employeeHoliday->holiday_date);
        $year = $date->year;
        $employeeHoliday->delete();
        $this->attendance->recalculate($employee, $date);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'That day is a working day again for '.$employee->full_name.'.']);
        }

        return redirect()
            ->route('holidays.index', ['year' => $year, 'month' => $date->month])
            ->with('success', 'Paid day removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Request $request, int $year, int $month): array
    {
        $companyId = $this->companyId();

        return [
            'year' => $year,
            'month' => $month,
            'holidays' => Holiday::query()
                ->visibleTo($request->user())
                ->where('company_id', $companyId)
                ->whereYear('holiday_date', $year)
                ->whereMonth('holiday_date', $month)
                ->orderBy('holiday_date')
                ->get(),
            'employeeHolidays' => EmployeeHoliday::query()
                ->whereHas('employee', function ($query) use ($request, $companyId) {
                    $query->visibleTo($request->user())->where('company_id', $companyId);
                })
                ->whereYear('holiday_date', $year)
                ->whereMonth('holiday_date', $month)
                ->with('employee')
                ->orderBy('holiday_date')
                ->get(),
            'employees' => Employee::query()
                ->visibleTo($request->user())
                ->where('company_id', $companyId)
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'first_name', 'last_name']),
            'canEdit' => $request->user()->hasPermission('holidays.manage'),
            'kindMeta' => Holiday::KINDS,
            'kindHelp' => Holiday::KIND_HELP,
        ];
    }

    private function refreshCompany(int $companyId, Carbon $date): void
    {
        Employee::query()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereDoesntHave('employmentStatus')
                    ->orWhereHas('employmentStatus', fn ($status) => $status->where('is_exit', false));
            })
            ->orderBy('id')
            ->each(fn (Employee $employee) => $this->attendance->recalculate($employee, $date));
    }
}
