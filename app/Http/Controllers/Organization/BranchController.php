<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    use GuardsCompany;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);
        $branches = Branch::query()->with('company')->visibleTo($request->user())
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('organization.branches.index', compact('branches'));
    }

    public function create(): View
    {
        $this->authorize('create', Branch::class);

        return view('organization.branches.form', ['branch' => null, 'companies' => $this->visibleCompanies()]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);
        $this->guardCompany((int) $request->integer('company_id'));
        Branch::query()->create($this->payload($request));

        return redirect()->route('branches.index')->with('success', 'Branch saved.');
    }

    public function edit(Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('organization.branches.form', ['branch' => $branch, 'companies' => $this->visibleCompanies()]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);
        $this->guardCompany((int) $request->integer('company_id'));
        $branch->update($this->payload($request));

        return redirect()->route('branches.index')->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);
        try {
            $branch->delete();
        } catch (QueryException) {
            return back()->with('error', 'This branch is in use and cannot be deleted.');
        }

        return back()->with('success', 'Branch deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(BranchRequest $request): array
    {
        $data = $request->safe()->except('is_active');
        $data['code'] = strtoupper((string) $data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
