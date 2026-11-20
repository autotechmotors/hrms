<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Department;
use App\Models\SalarySheet;
use App\Models\User;
use App\Notifications\SalarySheetNotice;
use App\Services\AccessScope;
use App\Services\Operations\SalarySheetBuilder;
use App\Services\Operations\SalarySheetExporter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalarySheetController extends Controller
{
    use UsesWorkspace;

    public function __construct(
        private readonly SalarySheetBuilder $builder,
        private readonly SalarySheetExporter $exporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAny('salary.view', 'salary.decide', 'payroll.process');
        $user = $request->user();
        $departments = Department::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $selected = (int) $request->input('department_id', 0);
        if ($selected === 0) {
            $selected = (int) $departments->firstWhere('code', 'SEAT')?->id;
        }
        if ($selected === 0) {
            $selected = (int) ($departments->first()->id ?? 0);
        }

        $sheets = $this->history($request);
        $canDecide = $user->hasPermission('salary.decide');
        if ($request->ajax() || $request->boolean('partial')) {
            return view('operations.salary-sheets._history', [
                'sheets' => $sheets,
                'canDecide' => $canDecide,
            ]);
        }

        $people = $selected > 0
            ? $this->peopleFor($request, $selected)
            : collect();

        return view('operations.salary-sheets.index', [
            'departments' => $departments,
            'selected' => $selected,
            'people' => $people,
            'sheets' => $sheets,
            'canGenerate' => $user->hasPermission('salary.manage') || $user->hasPermission('payroll.process'),
            'canDecide' => $canDecide,
            'starts' => $request->input('starts_on', '2026-11-02'),
            'ends' => $request->input('ends_on', '2026-11-06'),
        ]);
    }

    public function people(Request $request): View
    {
        $this->authorizeAny('salary.view', 'salary.manage', 'payroll.process');
        $departmentId = (int) $request->input('department_id');
        abort_unless($departmentId > 0 && Department::query()->visibleTo($request->user())->whereKey($departmentId)->exists(), 404);

        return view('operations.salary-sheets._people', [
            'people' => $this->peopleFor($request, $departmentId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAny('salary.manage', 'payroll.process');
        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'department_id' => ['required', 'integer'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
        ]);

        $start = Carbon::parse($data['starts_on'])->startOfDay();
        $end = Carbon::parse($data['ends_on'])->startOfDay();
        if ($end->gt($start->copy()->addDays(30))) {
            return back()->withErrors(['ends_on' => 'Choose 31 days or fewer. One sheet covers one month.'])->withInput();
        }

        $department = Department::query()->visibleTo($request->user())->whereKey($data['department_id'])->first();
        abort_unless($department, 403);

        $allowed = $this->activeEmployees()
            ->where('department_id', $department->id)
            ->whereIn('id', $data['employee_ids'])
            ->pluck('employees.id')
            ->all();

        if ($allowed === []) {
            return back()->withErrors(['employee_ids' => 'Choose at least one person in this department.'])->withInput();
        }

        $sheet = $this->builder->make(
            (int) $department->company_id,
            (int) $department->id,
            $allowed,
            $start->toDateString(),
            $end->toDateString(),
            $request->user()->id,
            $department->name,
        );

        $this->notify($sheet, 'requested', $request->user()->id);

        return redirect()
            ->route('salary-sheets.show', $sheet)
            ->with('status', 'Salary request sent. The accountant can approve it, or reject it with a reason.');
    }

    public function approve(Request $request, SalarySheet $salarySheet): JsonResponse|RedirectResponse
    {
        return $this->decide($request, $salarySheet, 'approved', null);
    }

    public function reject(Request $request, SalarySheet $salarySheet): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return $this->decide($request, $salarySheet, 'rejected', trim($data['reason']));
    }

    public function show(Request $request, SalarySheet $salarySheet): View
    {
        $this->authorizeAny('salary.view', 'salary.decide', 'payroll.process');
        $this->guard($request, $salarySheet);
        $salarySheet->load(['lines.employee.department', 'lines.employee.designation', 'department', 'company', 'author', 'decider']);

        return view('operations.salary-sheets.show', [
            'sheet' => $salarySheet,
            'canDecide' => $request->user()->hasPermission('salary.decide'),
        ]);
    }

    public function excel(Request $request, SalarySheet $salarySheet)
    {
        $this->authorizeAny('salary.view', 'salary.decide', 'payroll.process');
        $this->guard($request, $salarySheet);

        return $this->exporter->excel($salarySheet);
    }

    public function pdf(Request $request, SalarySheet $salarySheet)
    {
        $this->authorizeAny('salary.view', 'salary.decide', 'payroll.process');
        $this->guard($request, $salarySheet);

        return $this->exporter->pdf($salarySheet);
    }

    private function decide(Request $request, SalarySheet $sheet, string $status, ?string $reason): JsonResponse|RedirectResponse
    {
        $this->authorizeAny('salary.decide');
        $this->guard($request, $sheet);

        if (! $sheet->isPending()) {
            $message = 'This salary request is already decided.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['status' => $message]);
        }

        DB::transaction(function () use ($sheet, $status, $request, $reason): void {
            $sheet->update([
                'status' => $status,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => $reason,
            ]);
            if ($status === 'approved') {
                $this->builder->settleAdvances($sheet);
            }
        });
        $sheet->load(['author', 'decider']);
        $this->notify($sheet, $status, $request->user()->id);

        $message = $status === 'approved'
            ? 'Salary approved.'
            : 'Salary rejected.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'decision' => view('operations.salary-sheets._decision', [
                    'sheet' => $sheet,
                    'canDecide' => true,
                ])->render(),
            ]);
        }

        return back()->with('status', $message);
    }

    private function notify(SalarySheet $sheet, string $event, ?int $exceptUserId = null): void
    {
        $sheet->loadMissing('author');
        $companyId = (int) $sheet->company_id;
        $scope = app(AccessScope::class);

        $recipients = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['accounts', 'super-admin', 'hr-admin']))
            ->get()
            ->filter(function (User $user) use ($scope, $companyId, $exceptUserId) {
                if ($exceptUserId && (int) $user->id === $exceptUserId) {
                    return false;
                }

                return $user->isSuperAdmin() || in_array($companyId, $scope->companyIds($user), true);
            });

        if (in_array($event, ['approved', 'rejected'], true) && $sheet->author && (int) $sheet->author->id !== (int) $exceptUserId) {
            $recipients->push($sheet->author);
        }

        $recipients->unique('id')->each(fn (User $user) => $user->notify(new SalarySheetNotice($sheet, $event)));
    }

    private function history(Request $request)
    {
        $query = SalarySheet::query()
            ->where('company_id', $this->companyId())
            ->with('department')
            ->withCount('lines')
            ->withSum('lines', 'net');
        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('lines.employee', function ($people) use ($search) {
                        $people->where('employee_code', 'like', '%'.$search.'%')
                            ->orWhere('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%');
                    });
            });
        }
        $status = (string) $request->input('status', '');
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        return $query->latest('id')->paginate(12)->withQueryString();
    }

    private function peopleFor(Request $request, int $departmentId)
    {
        return $this->activeEmployees()
            ->where('department_id', $departmentId)
            ->reorder('employees.employee_code')
            ->get(['employees.id', 'employees.employee_code', 'employees.first_name', 'employees.last_name']);
    }

    private function guard(Request $request, SalarySheet $sheet): void
    {
        abort_unless((int) $sheet->company_id === $this->companyId(), 404);
    }
}
