<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function store(EmployeeDocumentRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('view', $employee);
        $file = $request->file('document');
        $path = $file->store('employee-documents/'.$employee->id, 'local');

        EmployeeDocument::query()->create([
            'employee_id' => $employee->id,
            'title' => $request->string('title')->toString(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->authorize('view', $employee);
        abort_unless($requestUserCanView($employee, $document), 404);
        abort_unless(request()->user()->hasPermission('employee_documents.view'), 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroy(Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        abort_unless(request()->user()->hasPermission('employee_documents.delete'), 403);
        $this->authorize('view', $employee);
        abort_unless((int) $document->employee_id === (int) $employee->id, 404);
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }
}

function requestUserCanView(Employee $employee, EmployeeDocument $document): bool
{
    return (int) $document->employee_id === (int) $employee->id;
}
