<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Services\Operations\MonthReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    use UsesWorkspace;

    public function __construct(private readonly MonthReport $report) {}

    public function index(Request $request): View|Response
    {
        $this->authorizeAny('reports.view');
        $year = max(1990, min(2100, (int) $request->input('year', now()->year)));
        $monthNumber = max(1, min(12, (int) $request->input('month', now()->month)));
        $month = Carbon::create($year, $monthNumber, 1)->startOfMonth();
        $companyId = $this->companyId();
        $departments = $this->report->departments($request->user(), $companyId);
        $departmentId = (int) $request->input('department_id');
        if (! $departments->contains('id', $departmentId)) {
            $departmentId = 0;
        }

        $data = [
            'month' => $month,
            'departmentId' => $departmentId,
            'departments' => $departments,
            'report' => $this->report->make($request->user(), $companyId, $month, $departmentId ?: null),
        ];

        if ($request->boolean('partial') && $request->ajax()) {
            return response(view('operations.reports._body', $data)->render());
        }

        return view('operations.reports.index', $data);
    }
}
