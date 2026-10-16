<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkCalendarRequest;
use App\Models\Branch;
use App\Models\WorkCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkCalendarController extends Controller
{
    use GuardsCompany;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', WorkCalendar::class);
        $calendars = WorkCalendar::query()->with(['company', 'branch'])->visibleTo($request->user())->orderByDesc('effective_from')->paginate(15);

        return view('organization.calendars.index', compact('calendars'));
    }

    public function create(): View
    {
        $this->authorize('create', WorkCalendar::class);

        return view('organization.calendars.form', [
            'calendar' => null,
            'companies' => $this->visibleCompanies(),
            'branches' => Branch::query()->visibleTo(request()->user())->orderBy('name')->get(),
            'workingDays' => [1, 2, 3, 4, 5],
        ]);
    }

    public function store(WorkCalendarRequest $request): RedirectResponse
    {
        $this->authorize('create', WorkCalendar::class);
        $this->persist(new WorkCalendar(), $request);

        return redirect()->route('work-calendars.index')->with('success', 'Work calendar saved.');
    }

    public function edit(WorkCalendar $workCalendar): View
    {
        $this->authorize('update', $workCalendar);

        return view('organization.calendars.form', [
            'calendar' => $workCalendar->load('days'),
            'companies' => $this->visibleCompanies(),
            'branches' => Branch::query()->visibleTo(request()->user())->orderBy('name')->get(),
            'workingDays' => $workCalendar->days->where('is_working', true)->pluck('weekday')->all(),
        ]);
    }

    public function update(WorkCalendarRequest $request, WorkCalendar $workCalendar): RedirectResponse
    {
        $this->authorize('update', $workCalendar);
        $this->persist($workCalendar, $request);

        return redirect()->route('work-calendars.index')->with('success', 'Work calendar updated.');
    }

    public function destroy(WorkCalendar $workCalendar): RedirectResponse
    {
        $this->authorize('delete', $workCalendar);
        $workCalendar->delete();

        return back()->with('success', 'Work calendar deleted.');
    }

    private function persist(WorkCalendar $calendar, WorkCalendarRequest $request): void
    {
        $this->guardCompany((int) $request->integer('company_id'));
        $branchId = $request->input('branch_id');
        if ($branchId) {
            $branch = Branch::query()->findOrFail($branchId);
            if ((int) $branch->company_id !== (int) $request->integer('company_id')) {
                throw ValidationException::withMessages(['branch_id' => 'Select a branch from the chosen company.']);
            }
        }

        DB::transaction(function () use ($calendar, $request, $branchId): void {
            $calendar->fill([
                'company_id' => $request->integer('company_id'),
                'branch_id' => $branchId ?: null,
                'name' => $request->string('name')->toString(),
                'effective_from' => $request->date('effective_from'),
                'effective_to' => $request->input('effective_to') ?: null,
                'is_active' => $request->boolean('is_active'),
            ])->save();

            $calendar->days()->delete();
            $working = array_map('intval', $request->input('working_days', []));
            foreach (range(0, 6) as $weekday) {
                $calendar->days()->create([
                    'weekday' => $weekday,
                    'is_working' => in_array($weekday, $working, true),
                ]);
            }
        });
    }
}
