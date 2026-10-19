<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HolidayController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorizeView();
        $year = (int) $request->input('year', now()->year);
        $clamped = max(1990, min(2100, $year));
        if ($clamped !== $year) {
            return redirect()->route('holidays.index', ['year' => $clamped]);
        }

        return view('operations.holidays.index', [
            'year' => $year,
            'holidays' => Holiday::query()
                ->visibleTo($request->user())
                ->where('company_id', $this->companyId())
                ->whereYear('holiday_date', $year)
                ->orderBy('holiday_date')
                ->get(),
            'canEdit' => $request->user()->hasPermission('employees.edit'),
            'kindMeta' => Holiday::KINDS,
            'kindHelp' => Holiday::KIND_HELP,
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

        $year = Carbon::parse($data['holiday_date'])->year;

        return redirect()
            ->route('holidays.index', ['year' => $year])
            ->with('success', 'Holiday added.');
    }

    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->authorizePeople();
        abort_unless((int) $holiday->company_id === $this->companyId(), 404);
        $year = $holiday->holiday_date?->year ?? now()->year;
        $holiday->delete();

        return redirect()
            ->route('holidays.index', ['year' => $year])
            ->with('success', 'Holiday removed.');
    }
}
