<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Company::class);

        $companies = Company::query()
            ->visibleTo($request->user())
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('organization.companies.index', compact('companies'));
    }

    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('organization.companies.form', ['company' => null]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->create($this->payload($request));
        $this->storeLogo($request, $company);

        return redirect()->route('companies.index')->with('success', 'Company saved.');
    }

    public function edit(Company $company): View
    {
        $this->authorize('update', $company);

        return view('organization.companies.form', compact('company'));
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($this->payload($request));
        $this->storeLogo($request, $company);

        return redirect()->route('companies.index')->with('success', 'Company updated.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        try {
            $company->delete();
        } catch (QueryException) {
            return back()->with('error', 'This company still has related records and cannot be deleted.');
        }

        return back()->with('success', 'Company deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CompanyRequest $request): array
    {
        $data = $request->safe()->except(['logo', 'is_active']);
        $data['code'] = strtoupper((string) $data['code']);
        $data['is_active'] = $request->boolean('is_active');
        $data['primary_color'] = $data['primary_color'] ?: '#1d4ed8';

        return $data;
    }

    private function storeLogo(CompanyRequest $request, Company $company): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }

        if ($company->logo_path) {
            Storage::disk('local')->delete($company->logo_path);
        }

        $company->update([
            'logo_path' => $request->file('logo')->store('company-logos', 'local'),
        ]);
    }
}
