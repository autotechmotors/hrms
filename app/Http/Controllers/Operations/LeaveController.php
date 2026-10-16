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

        return view('operations.leave.index', [
            'types' => LeaveType::query()->visibleTo($request->user())->where('company_id', $companyId)->orderBy('name')->get(),
            'requests' => LeaveRequest::query()
                ->whereIn('employee_id', $employeeIds)
                ->with(['employee', 'leaveType'])
                ->latest()
                ->paginate(20),
            'employees' => $this->activeEmployees()->get(),
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

        return back()->with('success', 'Leave type added. Annual days of 0 means no yearly cap.');
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

        return back()->with('success', 'Leave recorded. Approve it when the days should count.');
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

        return back()->with('success', 'Leave '.$data['decision'].'.');
    }

    private function assertNoOverlap(int $employeeId, Carbon $start, Carbon $end): void
    {
        $overlap = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => 'This employee already has leave covering those dates.']);
        }
    }
}
