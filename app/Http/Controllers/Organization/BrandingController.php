<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandingController extends Controller
{
    use GuardsCompany;

    public function edit(Request $request): View
    {
        abort_unless($request->user()->hasPermission('branding.edit'), 403);
        $companies = $this->visibleCompanies();
        $company = $companies->firstWhere('id', (int) $request->query('company')) ?? $companies->first();
        abort_unless($company, 404);

        return view('organization.branding.edit', compact('company', 'companies'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('branding.edit'), 403);
        $this->guardCompany($company->id);

        $data = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $company->primary_color = $data['primary_color'];
        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('local')->delete($company->logo_path);
            }
            $company->logo_path = $request->file('logo')->store('company-logos', 'local');
        }
        $company->save();

        return redirect()->route('branding.edit', ['company' => $company->id])->with('success', 'Branding updated.');
    }
}
