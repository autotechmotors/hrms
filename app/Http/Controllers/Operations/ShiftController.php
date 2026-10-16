<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShiftController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $start = Carbon::parse($request->input('week', now()->startOfWeek()->toDateString()))->startOfDay();
        $dates = collect(range(0, 6))->map(fn (int $offset) => $start->copy()->addDays($offset));
        $employees = $this->activeEmployees()->get();
        $rosters = ShiftRoster::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$dates->first()->toDateString(), $dates->last()->toDateString()])
            ->get()
            ->groupBy(fn (ShiftRoster $roster) => $roster->employee_id.'|'.$roster->work_date->toDateString());

        return view('operations.shifts.index', [
            'start' => $start,
            'dates' => $dates,
            'employees' => $employees,
            'rosters' => $rosters,
            'shifts' => Shift::query()->visibleTo($request->user())->where('company_id', $this->companyId())->orderBy('name')->get(),
            'canEdit' => $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $companyId = $this->companyId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', Rule::unique('shifts', 'code')->where('company_id', $companyId)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_flexible' => ['nullable', 'boolean'],
        ]);

        Shift::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'break_minutes' => $data['break_minutes'] ?? 0,
            'is_flexible' => $request->boolean('is_flexible'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Shift added.');
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $this->authorizePeople();
        abort_unless((int) $shift->company_id === $this->companyId(), 404);
        $shift->delete();

        return back()->with('success', 'Shift removed.');
    }

    public function roster(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer'],
            'is_off' => ['nullable', 'boolean'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        $shiftId = $data['shift_id'] ?: null;
        if ($shiftId) {
            Shift::query()->visibleTo($request->user())->where('company_id', $this->companyId())->findOrFail($shiftId);
        }
        $off = $request->boolean('is_off');

        if (! $shiftId && ! $off) {
            ShiftRoster::query()->where('employee_id', $employee->id)->whereDate('work_date', $data['work_date'])->delete();
        } else {
            ShiftRoster::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $data['work_date']],
                ['shift_id' => $off ? null : $shiftId, 'is_off' => $off],
            );
        }

        return back()->with('success', 'Roster updated.');
    }
}
