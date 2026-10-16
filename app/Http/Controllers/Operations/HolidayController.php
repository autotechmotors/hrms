<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HolidayController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();
        $year = (int) $request->input('year', now()->year);

        return view('operations.holidays.index', [
            'year' => $year,
            'holidays' => Holiday::query()
                ->visibleTo($request->user())
                ->where('company_id', $this->companyId())
                ->whereYear('holiday_date', $year)
                ->orderBy('holiday_date')
                ->get(),
            'canEdit' => $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePeople();
        $companyId = $this->companyId();
        $data = $request->validate([
            'holiday_date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['public', 'restricted', 'optional'])],
        ]);

        Holiday::query()->create([
            'company_id' => $companyId,
            'holiday_date' => $data['holiday_date'],
            'name' => $data['name'],
            'kind' => $data['kind'],
        ]);

        return back()->with('success', 'Holiday added.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $this->authorizePeople();
        abort_unless((int) $holiday->company_id === $this->companyId(), 404);
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
