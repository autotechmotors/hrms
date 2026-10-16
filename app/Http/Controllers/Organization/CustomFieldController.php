<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Concerns\GuardsCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFieldRequest;
use App\Models\EmployeeCustomField;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    use GuardsCompany;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmployeeCustomField::class);
        $fields = EmployeeCustomField::query()->with('company')->visibleTo($request->user())->orderBy('sort_order')->paginate(15);

        return view('organization.custom-fields.index', compact('fields'));
    }

    public function create(): View
    {
        $this->authorize('create', EmployeeCustomField::class);

        return view('organization.custom-fields.form', ['field' => null, 'companies' => $this->visibleCompanies()]);
    }

    public function store(CustomFieldRequest $request): RedirectResponse
    {
        $this->authorize('create', EmployeeCustomField::class);
        EmployeeCustomField::query()->create($this->payload($request));

        return redirect()->route('custom-fields.index')->with('success', 'Custom field saved.');
    }

    public function edit(EmployeeCustomField $customField): View
    {
        $this->authorize('update', $customField);

        return view('organization.custom-fields.form', ['field' => $customField, 'companies' => $this->visibleCompanies()]);
    }

    public function update(CustomFieldRequest $request, EmployeeCustomField $customField): RedirectResponse
    {
        $this->authorize('update', $customField);
        $customField->update($this->payload($request));

        return redirect()->route('custom-fields.index')->with('success', 'Custom field updated.');
    }

    public function destroy(EmployeeCustomField $customField): RedirectResponse
    {
        $this->authorize('delete', $customField);
        try {
            $customField->delete();
        } catch (QueryException) {
            return back()->with('error', 'This field already has employee values and cannot be deleted.');
        }

        return back()->with('success', 'Custom field deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CustomFieldRequest $request): array
    {
        $this->guardCompany((int) $request->integer('company_id'));
        $options = null;
        if ($request->string('field_type')->toString() === 'select') {
            $options = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('options')))));
        }

        return [
            'company_id' => $request->integer('company_id'),
            'name' => $request->string('name')->toString(),
            'code' => strtoupper($request->string('code')->toString()),
            'field_type' => $request->string('field_type')->toString(),
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
