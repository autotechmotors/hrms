<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocationRequest;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationController extends Controller
{
    use GuardsCompany;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Location::class);
        $locations = Location::query()->with(['company', 'branch'])->visibleTo($request->user())
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('organization.locations.index', compact('locations'));
    }

    public function create(): View
    {
        $this->authorize('create', Location::class);

        return view('organization.locations.form', [
            'location' => null,
            'companies' => $this->visibleCompanies(),
            'branches' => Branch::query()->visibleTo(request()->user())->orderBy('name')->get(),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $this->authorize('create', Location::class);
        Location::query()->create($this->payload($request));

        return redirect()->route('locations.index')->with('success', 'Location saved.');
    }

    public function edit(Location $location): View
    {
        $this->authorize('update', $location);

        return view('organization.locations.form', [
            'location' => $location,
            'companies' => $this->visibleCompanies(),
            'branches' => Branch::query()->visibleTo(request()->user())->orderBy('name')->get(),
        ]);
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);
        $location->update($this->payload($request));

        return redirect()->route('locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $this->authorize('delete', $location);
        try {
            $location->delete();
        } catch (QueryException) {
            return back()->with('error', 'This location is in use and cannot be deleted.');
        }

        return back()->with('success', 'Location deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LocationRequest $request): array
    {
        $this->guardCompany((int) $request->integer('company_id'));
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        if ((int) $branch->company_id !== (int) $request->integer('company_id')) {
            throw ValidationException::withMessages(['branch_id' => 'Select a branch from the chosen company.']);
        }

        $data = $request->safe()->except('is_active');
        $data['code'] = strtoupper((string) $data['code']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
