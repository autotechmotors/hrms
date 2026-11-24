<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Advance;
use App\Models\User;
use App\Notifications\AdvanceNotice;
use App\Services\AccessScope;
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
        $this->authorizeAny('advances.view', 'advances.decide', 'payroll.process');
        $partial = $request->ajax() || $request->boolean('partial');
        $employeeIds = $this->activeEmployees()->pluck('id');

        $query = Advance::query()
            ->whereIn('employee_id', $employeeIds->all() ?: [0])
            ->with(['employee.department', 'author', 'decider']);
        $this->applySearch($query, $request);

        $stats = [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'deducted' => (clone $query)->where('status', 'completed')->count(),
            'balance' => (float) (clone $query)->where('status', 'active')->sum('balance'),
        ];

        if (in_array($request->input('status'), ['pending', 'active', 'rejected', 'closed', 'completed'], true)) {
            $query->where('status', $request->input('status'));
        }

        $user = $request->user();

        return view($partial ? 'operations.advances._list' : 'operations.advances.index', [
            'advances' => $query->latest()->paginate(20)->withQueryString(),
            'employees' => $partial ? collect() : $this->activeEmployees()->get(),
            'canEdit' => $user->hasPermission('advances.manage') || $user->hasPermission('payroll.process'),
            'canDecide' => $user->hasPermission('advances.decide'),
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
        $advance = Advance::query()->create([
            'employee_id' => $employee->id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'amount' => $data['amount'],
            'emi' => $data['amount'],
            'balance' => $data['amount'],
            'starts_on' => $given->toDateString(),
            'status' => 'pending',
        ]);
        $this->notify($advance, 'pending', $request->user()->id);

        $message = 'Advance request sent for '.$given->format('F Y').'. The accountant must approve it before salary can deduct it.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Advance $advance): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('advances.manage') || $request->user()->hasPermission('payroll.process'), 403);
        $this->guard($advance);
        if (! $advance->isPending()) {
            $message = 'Only a pending advance can be edited.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['status' => $message]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
        $amount = round((float) $data['amount'], 2);
        $advance->update([
            'title' => $data['title'],
            'amount' => $amount,
            'emi' => $amount,
            'balance' => $amount,
        ]);

        $message = 'Advance updated.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, Advance $advance): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('advances.manage') || $request->user()->hasPermission('payroll.process'), 403);
        $this->guard($advance);
        if (! in_array($advance->status, ['pending', 'rejected', 'closed'], true)) {
            $message = 'Only a pending, rejected, or closed advance can be deleted.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['status' => $message]);
        }

        $advance->delete();

        $message = 'Advance deleted.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function close(Request $request, Advance $advance): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('advances.manage') || $request->user()->hasPermission('payroll.process'), 403);
        $this->guard($advance);
        if ($advance->status !== 'active') {
            $message = 'Only an approved advance can be closed.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['status' => $message]);
        }

        $advance->update(['status' => 'closed']);

        $message = 'Advance closed. It will not be deducted from salary.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function approve(Request $request, Advance $advance): RedirectResponse|JsonResponse
    {
        return $this->decide($request, $advance, 'active', null);
    }

    public function reject(Request $request, Advance $advance): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return $this->decide($request, $advance, 'rejected', trim($data['reason']));
    }

    private function decide(Request $request, Advance $advance, string $status, ?string $reason): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->hasPermission('advances.decide'), 403);
        $this->guard($advance);

        if (! $advance->isPending()) {
            $message = 'This advance request is already decided.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['status' => $message]);
        }

        $advance->update([
            'status' => $status,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $reason,
        ]);
        $advance->load(['employee', 'author', 'decider']);
        $this->notify($advance, $status === 'active' ? 'approved' : 'rejected', $request->user()->id);

        $message = $status === 'active' ? 'Advance approved.' : 'Advance rejected.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    private function guard(Advance $advance): void
    {
        $advance->loadMissing('employee');
        abort_unless($advance->employee && (int) $advance->employee->company_id === $this->companyId(), 404);
    }

    private function notify(Advance $advance, string $event, ?int $exceptUserId = null): void
    {
        $advance->loadMissing(['employee', 'author']);
        $companyId = (int) ($advance->employee?->company_id ?? 0);
        if ($companyId === 0) {
            return;
        }

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

        if (in_array($event, ['approved', 'rejected'], true) && $advance->author && (int) $advance->author->id !== (int) $exceptUserId) {
            $recipients->push($advance->author);
        }

        $recipients->unique('id')->each(fn (User $user) => $user->notify(new AdvanceNotice($advance, $event)));
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
