<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveNotice;
use App\Services\AccessScope;
use App\Services\Operations\WorkCalendarResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View|Response
    {
        $this->authorizeAny('leave.view');
        $data = $this->pageData($request);
        if ($request->boolean('partial') && $request->ajax()) {
            return response(view('operations.leave._body', $data)->render());
        }

        return view('operations.leave.index', $data);
    }

    public function storeType(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('leave.decide');
        $companyId = $this->companyId();
        $data = $this->typeData($request, $companyId);
        $targets = $data['department_ids'] === [] ? [null] : $data['department_ids'];

        DB::transaction(function () use ($companyId, $data, $targets, $request) {
            foreach ($targets as $departmentId) {
                $this->assertTypeCodeIsFree($companyId, $departmentId, $data['code']);
            }
            foreach ($targets as $departmentId) {
                $this->makeType($companyId, $departmentId, $data, ! $request->boolean('cuts_pay'));
            }
        });

        $message = count($targets) > 1
            ? 'Leave type saved for the selected departments.'
            : ($targets[0] === null ? 'Leave type saved for all departments.' : 'Leave type saved for this department.');

        return $this->done($request, $message);
    }

    public function updateType(Request $request, LeaveType $leaveType): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('leave.decide');
        abort_unless((int) $leaveType->company_id === $this->companyId(), 404);
        $data = $this->typeData($request, (int) $leaveType->company_id);
        $ids = $data['department_ids'];
        $current = $leaveType->department_id === null ? null : (int) $leaveType->department_id;
        if ($ids === []) {
            $keep = null;
            $extra = [];
        } elseif ($current !== null && in_array($current, $ids, true)) {
            $keep = $current;
            $extra = array_values(array_filter($ids, fn (int $id) => $id !== $current));
        } else {
            $keep = $ids[0];
            $extra = array_slice($ids, 1);
        }

        DB::transaction(function () use ($leaveType, $data, $keep, $extra, $request) {
            $this->assertTypeCodeIsFree((int) $leaveType->company_id, $keep, $data['code'], $leaveType->id);
            foreach ($extra as $departmentId) {
                $this->assertTypeCodeIsFree((int) $leaveType->company_id, $departmentId, $data['code']);
            }
            $leaveType->update([
                'department_id' => $keep,
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'is_paid' => ! $request->boolean('cuts_pay'),
                'counts_weekly_off' => false,
                'counts_holidays' => false,
            ]);
            foreach ($extra as $departmentId) {
                $this->makeType((int) $leaveType->company_id, $departmentId, $data, ! $request->boolean('cuts_pay'));
            }
        });

        return $this->done($request, 'Leave type updated.');
    }

    public function destroyType(Request $request, LeaveType $leaveType): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('leave.decide');
        abort_unless((int) $leaveType->company_id === $this->companyId(), 404);
        if ($leaveType->requests()->exists()) {
            $suffix = '-'.$leaveType->id;
            $leaveType->update([
                'is_active' => false,
                'code' => substr($leaveType->code, 0, max(1, 40 - strlen($suffix))).$suffix,
            ]);

            return $this->done($request, $leaveType->name.' is closed. Past leave still shows it, and nobody can request it again.');
        }
        $leaveType->delete();

        return $this->done($request, 'Leave type deleted.');
    }

    public function store(Request $request, WorkCalendarResolver $calendar): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeAny('leave.view', 'leave.decide');
        [$employee, $type, $start, $end, $days, $reason] = $this->requestData($request, $calendar);

        if (! $user->hasPermission('leave.decide')) {
            $ownId = (int) ($user->employee?->id ?? 0);
            abort_unless($ownId === (int) $employee->id, 403);
        }

        $leave = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'days' => $days,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        $this->notify($leave->fresh(['employee.manager.user', 'employee.user', 'leaveType']), 'requested', $user->id);

        return $this->done($request, 'Leave request sent. Super Admin, HR Admin, and the parent can approve or reject it.');
    }

    public function update(Request $request, LeaveRequest $leaveRequest, WorkCalendarResolver $calendar): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('leave.decide');
        $this->visibleRequest($leaveRequest);
        [$employee, $type, $start, $end, $days, $reason] = $this->requestData($request, $calendar, (int) $leaveRequest->id);

        if ($leaveRequest->status === 'approved' && (float) $type->annual_days > 0) {
            $this->assertWithinYear($employee->id, $type, $start, $days, (int) $leaveRequest->id);
        }

        $leaveRequest->update([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'days' => $days,
            'reason' => $reason,
            'status' => 'pending',
            'decided_by' => null,
            'decided_at' => null,
            'decision_note' => null,
        ]);

        $this->notify($leaveRequest->fresh(['employee.manager.user', 'employee.user', 'leaveType']), 'requested', $request->user()->id);

        return $this->done($request, 'Leave updated. It is pending approval again.');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->visibleRequest($leaveRequest);
        $ownsPending = (int) ($user->employee?->id ?? 0) === (int) $leaveRequest->employee_id && $leaveRequest->status === 'pending';
        if (! $user->hasPermission('leave.decide') && ! $ownsPending) {
            $this->authorizeAny('leave.decide');
        }
        $leaveRequest->delete();

        return $this->done($request, 'Leave deleted.');
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        $this->authorizeAny('leave.view');
        $leaveRequest->load('employee.manager', 'leaveType');
        $this->visibleRequest($leaveRequest);
        $this->assertCanDecide($leaveRequest, $request->user());
        abort_unless($leaveRequest->status === 'pending', 422, 'Only a pending request can be decided.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['nullable', 'string', 'max:500', 'required_if:decision,rejected'],
        ]);

        if ($data['decision'] === 'approved' && (float) $leaveRequest->leaveType->annual_days > 0) {
            $this->assertWithinYear(
                (int) $leaveRequest->employee_id,
                $leaveRequest->leaveType,
                $leaveRequest->starts_on,
                (float) $leaveRequest->days,
                (int) $leaveRequest->id
            );
        }

        $leaveRequest->update([
            'status' => $data['decision'],
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision'] === 'rejected' ? $data['decision_note'] : null,
        ]);

        $this->notify($leaveRequest->fresh(['employee.user', 'leaveType', 'decider']), $data['decision'], $request->user()->id);

        return $this->done($request, $data['decision'] === 'approved' ? 'Leave approved.' : 'Leave rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Request $request): array
    {
        $companyId = $this->companyId();
        $user = $request->user();
        $employeeIds = $this->activeEmployees()->pluck('id');
        $status = trim((string) $request->input('status', ''));
        $q = trim((string) $request->input('q', ''));
        $typeId = $request->input('leave_type_id');
        $departmentId = $request->input('department_id');

        $types = LeaveType::query()
            ->visibleTo($user)
            ->where('company_id', $companyId)
            ->with('department')
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->where('department_id', (int) $departmentId))
            ->orderBy('name')
            ->get();

        $base = LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->with(['employee.department', 'employee.manager', 'leaveType.department']);

        $stats = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'types' => LeaveType::query()->visibleTo($user)->where('company_id', $companyId)->where('is_active', true)->count(),
        ];

        $requests = (clone $base)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($typeId, fn ($query) => $query->where('leave_type_id', (int) $typeId))
            ->when($departmentId, fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('department_id', (int) $departmentId)))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->whereHas('employee', function ($employee) use ($like) {
                    $employee->where('employee_code', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $own = $user->employee;
        $own?->loadMissing('department:id,name');

        return [
            'types' => $types,
            'typeChoices' => LeaveType::query()->visibleTo($user)->where('company_id', $companyId)->where('is_active', true)->with('department:id,name')->orderBy('name')->get(),
            'requests' => $requests,
            'stats' => $stats,
            'employees' => $this->activeEmployees()->with('department:id,name')->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name', 'department_id']),
            'departments' => Department::query()->visibleTo($user)->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'leave_type_id' => $typeId,
                'department_id' => $departmentId,
            ],
            'canEdit' => $user->hasPermission('leave.decide'),
            'teamIds' => $user->employee?->managedEmployeeIds() ?? collect(),
            'canApply' => $own && (int) $own->company_id === $companyId && ! $own->is_blocked,
            'ownEmployee' => $own,
            'actorId' => (int) $user->id,
        ];
    }

    /**
     * @return array{department_ids: array<int, int>, name: string, code: string, cuts_pay: string}
     */
    private function typeData(Request $request, int $companyId): array
    {
        $allowed = Department::query()->visibleTo($request->user())->where('company_id', $companyId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $raw = $request->has('department_ids') ? $request->input('department_ids') : $request->input('department_id');
        $ids = collect(is_array($raw) ? $raw : [$raw])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'cuts_pay' => ['required', Rule::in(['0', '1'])],
        ]);
        foreach ($ids as $id) {
            if (! in_array($id, $allowed, true)) {
                throw ValidationException::withMessages(['department_ids' => 'Choose a department from this company.']);
            }
        }
        $data['department_ids'] = $ids;

        return $data;
    }

    /**
     * @param  array{name: string, code: string}  $data
     */
    private function makeType(int $companyId, ?int $departmentId, array $data, bool $isPaid): void
    {
        LeaveType::query()->create([
            'company_id' => $companyId,
            'department_id' => $departmentId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'annual_days' => 0,
            'is_paid' => $isPaid,
            'counts_weekly_off' => false,
            'counts_holidays' => false,
            'is_active' => true,
        ]);
    }

    private function assertTypeCodeIsFree(int $companyId, ?int $departmentId, string $code, ?int $ignoreId = null): void
    {
        $taken = LeaveType::query()
            ->where('company_id', $companyId)
            ->where('code', strtoupper($code))
            ->when(
                $departmentId === null,
                fn ($query) => $query->whereNull('department_id'),
                fn ($query) => $query->where('department_id', $departmentId),
            )
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['code' => 'This code is already used for that department.']);
        }
    }

    /**
     * @return array{0: Employee, 1: LeaveType, 2: Carbon, 3: Carbon, 4: float, 5: string}
     */
    private function requestData(Request $request, WorkCalendarResolver $calendar, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $type = LeaveType::query()
            ->visibleTo($request->user())
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->findOrFail($data['leave_type_id']);
        if ($type->depar        atment_id && (int) $type->department_id !== (int) $employee->department_id) {
            throw ValidationException::withMessages(['leave_type_id' => 'Choose a leave type for this employee’s department.']);
        }
        $start = Carbon::parse($data['starts_on']);
        $end = Carbon::parse($data['ends_on']);
        $this->assertNoOverlap((int) $employee->id, $start, $end, $ignoreId);
        $days = $calendar->leaveDays((int) $employee->company_id, $start, $end, false, false);
        if ($days <= 0) {
            throw ValidationException::withMessages(['starts_on' => 'Those dates are all weekly offs or holidays.']);
        }

        return [$employee, $type, $start, $end, $days, $data['reason']];
    }

    private function visibleRequest(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing('employee');
        abort_unless($leaveRequest->employee && (int) $leaveRequest->employee->company_id === $this->companyId(), 404);
        $this->activeEmployees()->findOrFail($leaveRequest->employee_id);
    }

    private function assertCanDecide(LeaveRequest $leave, User $user): void
    {
        $ownId = (int) ($user->employee?->id ?? 0);
        if ($ownId === (int) $leave->employee_id && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages(['decision' => 'You cannot decide your own leave.']);
        }
        if ($user->hasPermission('leave.decide')) {
            return;
        }
        $actor = $user->employee;
        abort_unless($actor && $leave->employee && $actor->manages($leave->employee), 403);
    }

    private function assertWithinYear(int $employeeId, LeaveType $type, Carbon $start, float $days, int $ignoreId): void
    {
        $used = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $type->id)
            ->where('status', 'approved')
            ->whereYear('starts_on', $start->year)
            ->whereKeyNot($ignoreId)
            ->sum('days');
        if ((float) $used + $days > (float) $type->annual_days) {
            throw ValidationException::withMessages(['leave_type_id' => 'This would go past the yearly days set on the leave type.']);
        }
    }

    private function assertNoOverlap(int $employeeId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $overlap = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => 'This employee already has leave covering those dates.']);
        }
    }

    private function notify(LeaveRequest $leave, string $event, ?int $exceptUserId = null): void
    {
        $companyId = (int) ($leave->employee?->company_id ?? 0);
        $scope = app(AccessScope::class);
        $recipients = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super-admin', 'hr-admin']))
            ->get()
            ->filter(function (User $user) use ($scope, $companyId, $exceptUserId) {
                if ($exceptUserId && (int) $user->id === $exceptUserId) {
                    return false;
                }

                return $user->isSuperAdmin() || in_array($companyId, $scope->companyIds($user), true);
            });

        if ($event === 'requested') {
            $parent = $leave->employee?->manager?->user;
            if ($parent && $parent->is_active && (int) $parent->id !== (int) $exceptUserId) {
                $recipients->push($parent);
            }
        } else {
            $employeeUser = $leave->employee?->user;
            if ($employeeUser && (int) $employeeUser->id !== (int) $exceptUserId) {
                $recipients->push($employeeUser);
            }
        }

        $recipients->unique('id')->each(fn (User $user) => $user->notify(new LeaveNotice($leave, $event)));
    }

    private function done(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('leave.index', $this->filterQuery($request))->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterQuery(Request $request): array
    {
        return array_filter([
            'q' => $request->input('retain_q', $request->input('q')),
            'status' => $request->input('retain_status', $request->query('status')),
            'leave_type_id' => $request->input('retain_type', $request->query('leave_type_id')),
            'department_id' => $request->input('retain_department', $request->query('department_id')),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
