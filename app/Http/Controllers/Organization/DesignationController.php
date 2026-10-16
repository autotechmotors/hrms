<?php

namespace App\Http\Controllers\Organization;

use App\Http\Requests\CodedMasterRequest;
use App\Models\Designation;
use App\Models\EmployeeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DesignationController extends CodedMasterController
{
    protected function model(): string
    {
        return Designation::class;
    }

    protected function title(): string
    {
        return 'Designation';
    }

    protected function routeName(): string
    {
        return 'designations';
    }

    protected function permission(): string
    {
        return 'designations';
    }

    protected function routeParameter(): string
    {
        return 'designation';
    }

    protected function indexRelations(): array
    {
        return ['company', 'employeeType'];
    }

    protected function showsCategory(): bool
    {
        return true;
    }

    protected function categoryLink(): ?string
    {
        return route('employee-types.index');
    }

    protected function formExtras(?Model $record): array
    {
        return [
            'showCategory' => true,
            'categories' => EmployeeType::query()->visibleTo(request()->user())->orderBy('name')->get(),
        ];
    }

    protected function mutatePayload(array $data, CodedMasterRequest $request): array
    {
        $type = EmployeeType::query()->find($request->integer('employee_type_id'));

        if (! $type || (int) $type->company_id !== (int) $data['company_id']) {
            throw ValidationException::withMessages([
                'employee_type_id' => 'Choose a category from the same company.',
            ]);
        }

        $data['employee_type_id'] = $type->id;

        return $data;
    }
}
