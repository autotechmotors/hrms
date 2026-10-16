<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialYearRequest;
use App\Models\FinancialYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialYearController extends Controller
{
    use GuardsCompany;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FinancialYear::class);
        $years = FinancialYear::query()->with('company')->visibleTo($request->user())->orderByDesc('starts_on')->paginate(15);

        return view('organization.financial-years.index', compact('years'));
    }

    public function create(): View
    {
        $this->authorize('create', FinancialYear::class);

        return view('organization.financial-years.form', ['year' => null, 'companies' => $this->visibleCompanies()]);
    }

    public function store(FinancialYearRequest $request): RedirectResponse
    {
        $this->authorize('create', FinancialYear::class);
        $this->save(new FinancialYear(), $request);

        return redirect()->route('financial-years.index')->with('success', 'Financial year saved.');
    }

    public function edit(FinancialYear $financialYear): View
    {
        $this->authorize('update', $financialYear);

        return view('organization.financial-years.form', ['year' => $financialYear, 'companies' => $this->visibleCompanies()]);
    }

    public function update(FinancialYearRequest $request, FinancialYear $financialYear): RedirectResponse
    {
        $this->authorize('update', $financialYear);
        $this->save($financialYear, $request);

        return redirect()->route('financial-years.index')->with('success', 'Financial year updated.');
    }

    public function destroy(FinancialYear $financialYear): RedirectResponse
    {
        $this->authorize('delete', $financialYear);
        $financialYear->delete();

        return back()->with('success', 'Financial year deleted.');
    }

    private function save(FinancialYear $year, FinancialYearRequest $request): void
    {
        $this->guardCompany((int) $request->integer('company_id'));

        DB::transaction(function () use ($year, $request): void {
            if ($request->boolean('is_current')) {
                FinancialYear::query()
                    ->where('company_id', $request->integer('company_id'))
                    ->whereKeyNot($year->id ?? 0)
                    ->update(['is_current' => false]);
            }

            $year->fill([
                'company_id' => $request->integer('company_id'),
                'name' => $request->string('name')->toString(),
                'starts_on' => $request->date('starts_on'),
                'ends_on' => $request->date('ends_on'),
                'is_current' => $request->boolean('is_current'),
            ])->save();
        });
    }
}
