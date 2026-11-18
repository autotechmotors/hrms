<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Advance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvanceController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeAny('advances.view', 'payroll.process');
        $partial = $request->ajax() || $request->boolean('partial');
        $employeeIds = $this->activeEmployees()->pluck('id');

        $query = Advance::query()
            ->whereIn('employee_id', $employeeIds->all() ?: [0])
            ->with(['employee.department']);
        $this->applySearch($query, $request);

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'deducted' => (clone $query)->where('status', 'completed')->count(),
            'balance' => (float) (clone $query)->where('status', 'active')->sum('balance'),
        ];

        if (in_array($request->input('status'), ['active', 'closed', 'completed'], true)) {
            $query->where('status', $request->input('status'));
        }

        return view($partial ? 'operations.advances._list' : 'operations.advances.index', [
            'advances' => $query->latest()->paginate(20)->withQueryString(),
            'employees' => $partial ? collect() : $this->activeEmployees()->get(),
            'canEdit' => $request->user()->hasPermission('advances.manage') || $request->user()->hasPermission('payroll.process'),
            'stats' => $stats,
            'filters' => [
                'q' => trim((string) $request->input('q', '')),
                'status' => (string) $request->input('status', ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('advances.manage') || $request->user()->hasPermission('payroll.process'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $given = now();
        Advance::query()->create([
            'employee_id' => $employee->id,
            'title' => $data['title'],
            'amount' => $data['amount'],
            'emi' => $data['amount'],
            'balance' => $data['amount'],
            'starts_on' => $given->toDateString(),
            'status' => 'active',
        ]);

        $message = 'Advance recorded for '.$given->format('F Y').'. The full amount is deducted from that month’s salary when payroll is calculated.';
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function close(Request $request, Advance $advance): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('advances.manage') || $request->user()->hasPermission('payroll.process'), 403);
        $advance->load('employee');
        abort_unless($advance->employee && (int) $advance->employee->company_id === $this->companyId(), 404);
        $advance->update(['status' => 'closed']);

        $message = 'Advance closed. It will not be deducted from salary.';
        if ($request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    protected function applySearch(Builder $query, Request $request): void
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return;
        }

        $like = '%'.$q.'%';
        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('title', 'like', $like)
                ->orWhereHas('employee', function (Builder $employee) use ($like): void {
                    $employee->where('employee_code', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
        });
    }
}
