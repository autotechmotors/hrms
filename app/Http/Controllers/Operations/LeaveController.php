<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Operations\WorkCalendarResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeaveController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $companyId = $this->companyId();
        $employeeIds = $this->activeEmployees()->pluck('id');
        $status = trim((string) $request->input('status', ''));
        $q = trim((string) $request->input('q', ''));
        $typeId = $request->input('leave_type_id');

        $types = LeaveType::query()
            ->visibleTo($request->user())
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        $base = LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->with(['employee.department', 'leaveType']);

        $stats = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'types' => $types->count(),
        ];

        $requests = (clone $base)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($typeId, fn ($query) => $query->where('leave_type_id', (int) $typeId))
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

        return view('operations.leave.index', [
            'types' => $types,
            'requests' => $requests,
            'stats' => $stats,
            'employees' => $this->activeEmployees()->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'leave_type_id' => $typeId,
            ],
            'canEdit' => $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $companyId = $this->companyId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('leave_types', 'code')->where('company_id', $companyId)],
            'annual_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'is_paid' => ['nullable', 'boolean'],
            'counts_weekly_off' => ['nullable', 'boolean'],
            'counts_holidays' => ['nullable', 'boolean'],
        ]);

        LeaveType::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'annual_days' => $data['annual_days'] ?? 0,
            'is_paid' => $request->boolean('is_paid', true),
            'counts_weekly_off' => $request->boolean('counts_weekly_off'),
            'counts_holidays' => $request->boolean('counts_holidays'),
            'is_active' => true,
        ]);

        return redirect()->route('leave.index', $this->filterQuery($request))
            ->with('success', 'Leave type added. Annual days of 0 means no yearly cap.');
    }

    public function store(Request $request, WorkCalendarResolver $calendar): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $type = LeaveType::query()->visibleTo($request->user())->where('company_id', $this->companyId())->where('is_active', true)->findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['starts_on']);
        $end = Carbon::parse($data['ends_on']);
        $this->assertNoOverlap((int) $employee->id, $start, $end);
        $days = $calendar->leaveDays((int) $employee->company_id, $start, $end, (bool) $type->counts_weekly_off, (bool) $type->counts_holidays);
        if ($days <= 0) {
            throw ValidationException::withMessages(['starts_on' => 'Those dates are all weekly offs or holidays for this leave type.']);
        }

        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        return redirect()->route('leave.index', $this->filterQuery($request))
            ->with('success', 'Leave recorded. Approve it when the days should count.');
    }

    public function update(Request $request, LeaveRequest $leaveRequest, WorkCalendarResolver $calendar): RedirectResponse
    {
        $this->authorizePeople();
        $leaveRequest->load('employee');
        abort_unless($leaveRequest->employee && (int) $leaveRequest->employee->company_id === $this->companyId(), 404);
        $this->activeEmployees()->findOrFail($leaveRequest->employee_id);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $type = LeaveType::query()->visibleTo($request->user())->where('company_id', $this->companyId())->where('is_active', true)->findOrFail($data['leave_type_id']);
        $start = Carbon::parse($data['starts_on']);
        $end = Carbon::parse($data['ends_on']);
        $this->assertNoOverlap((int) $employee->id, $start, $end, (int) $leaveRequest->id);
        $days = $calendar->leaveDays((int) $employee->company_id, $start, $end, (bool) $type->counts_weekly_off, (bool) $type->counts_holidays);
        if ($days <= 0) {
            throw ValidationException::withMessages(['starts_on' => 'Those dates are all weekly offs or holidays for this leave type.']);
        }

        if ($leaveRequest->status === 'approved' && (float) $type->annual_days > 0) {
            $used = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->where('status', 'approved')
                ->whereYear('starts_on', $start->year)
                ->whereKeyNot($leaveRequest->id)
                ->sum('days');
            if ((float) $used + (float) $days > (float) $type->annual_days) {
                throw ValidationException::withMessages(['leave_type_id' => 'This would go past the yearly days set on the leave type.']);
            }
        }

        $leaveRequest->update([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'days' => $days,
            'reason' => $data['reason'],
            'status' => 'pending',
            'decided_by' => null,
            'decided_at' => null,
        ]);

        return redirect()->route('leave.index', $this->filterQuery($request))
            ->with('success', 'Leave updated. It is pending approval again.');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizePeople();
        $leaveRequest->load('employee');
        abort_unless($leaveRequest->employee && (int) $leaveRequest->employee->company_id === $this->companyId(), 404);
        $this->activeEmployees()->findOrFail($leaveRequest->employee_id);
        $leaveRequest->delete();

        return redirect()->route('leave.index', $this->filterQuery($request))
            ->with('success', 'Leave deleted.');
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorizePeople();
        $leaveRequest->load('employee', 'leaveType');
        abort_unless($leaveRequest->employee && (int) $leaveRequest->employee->company_id === $this->companyId(), 404);
        $this->activeEmployees()->findOrFail($leaveRequest->employee_id);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]]);

        if ($data['decision'] === 'approved' && (float) $leaveRequest->leaveType->annual_days > 0) {
            $used = LeaveRequest::query()
                ->where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('status', 'approved')
                ->whereYear('starts_on', $leaveRequest->starts_on->year)
                ->whereKeyNot($leaveRequest->id)
                ->sum('days');
            if ((float) $used + (float) $leaveRequest->days > (float) $leaveRequest->leaveType->annual_days) {
                throw ValidationException::withMessages(['decision' => 'This would go past the yearly days set on the leave type.']);
            }
        }

        $leaveRequest->update([
            'status' => $data['decision'],
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return redirect()->route('leave.index', $this->filterQuery($request))
            ->with('success', 'Leave '.$data['decision'].'.');
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

    /**
     * @return array<string, mixed>
     */
    private function filterQuery(Request $request): array
    {
        return array_filter([
            'q' => $request->input('retain_q', $request->input('q')),
            'status' => $request->input('retain_status', $request->query('status')),
            'leave_type_id' => $request->input('retain_type', $request->query('leave_type_id')),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
