<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Http\Requests\StoreGatePassRequest;
use App\Models\GatePass;
use App\Models\GatePassSetting;
use App\Services\Operations\GatePassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GatePassController extends Controller
{
    use UsesWorkspace;

    public function __construct(protected GatePassService $gatePasses) {}

    public function index(Request $request): View
    {
        $this->authorizeAny('gate_passes.view');
        GatePass::expireStale();

        $user = $request->user();
        $ids = $this->activeEmployees()->pluck('id');
        $base = GatePass::query()->forEmployees($ids)->with(['employee.department', 'decider']);

        $passes = (clone $base)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('today'), fn ($query) => $query->today())
            ->when($request->filled('date'), fn ($query) => $query->whereDate('date', $request->date('date')))
            ->when($request->integer('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('work_type'), fn ($query) => $query->where('work_type', $request->string('work_type')))
            ->when($request->filled('q'), fn ($query) => $query->search((string) $request->input('q')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $managed = $user->employee?->managedEmployeeIds() ?? collect();

        return view('operations.gate-passes.index', [
            'passes' => $passes,
            'stats' => [
                'pending' => (clone $base)->pending()->count(),
                'active' => (clone $base)->active()->count(),
                'approved_today' => (clone $base)->where('status', GatePass::STATUS_APPROVED)->whereDate('decided_at', now()->toDateString())->count(),
                'total' => (clone $base)->count(),
            ],
            'pendingToday' => $managed->isEmpty()
                ? collect()
                : GatePass::query()->with('employee.department')->forEmployees($managed)->pending()->today()->latest()->get(),
            'employees' => $this->activeEmployees()->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']),
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'status' => (string) $request->input('status', ''),
                'date' => (string) $request->input('date', ''),
                'employee_id' => $request->integer('employee_id') ?: null,
                'work_type' => (string) $request->input('work_type', ''),
                'today' => $request->boolean('today'),
            ],
            'canCreate' => $user->hasPermission('gate_passes.create'),
            'canDecide' => $user->hasPermission('gate_passes.decide'),
            'canManage' => $user->hasPermission('gate_passes.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAny('gate_passes.create');
        $settings = GatePassSetting::forCompany($this->companyId());
        $minutes = max(15, (int) $settings->default_duration_minutes);

        return view('operations.gate-passes.create', [
            'employees' => $this->activeEmployees()->get(['id', 'employee_code', 'first_name', 'middle_name', 'last_name']),
            'settings' => $settings,
            'selfEmployee' => $request->user()->employee,
            'defaultDuration' => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
        ]);
    }

    public function store(StoreGatePassRequest $request): RedirectResponse
    {
        $pass = $this->gatePasses->create(
            $request->user(),
            $request->validated(),
            $this->activeEmployees()->findOrFail($request->integer('employee_id'))
        );

        return redirect()->route('gate-passes.show', $pass)
            ->with('success', $pass->isPending()
                ? 'Gate Pass requested. Parent must approve before the OUT machine will allow exit.'
                : 'Gate Pass approved. OUT machine access is available until '.$pass->expires_at?->format('h:i A').'.');
    }

    public function show(GatePass $gatePass): View
    {
        $this->assertVisible($gatePass);
        GatePass::expireStale();
        $gatePass->refresh()->load(['employee.department', 'employee.manager', 'decider.employee', 'creator']);
        $user = request()->user();

        return view('operations.gate-passes.show', [
            'gatePass' => $gatePass,
            'canDecide' => $gatePass->isPending()
                && $user->hasPermission('gate_passes.decide')
                && $gatePass->employee
                && $user->canDecideGatePassFor($gatePass->employee),
        ]);
    }

    public function approve(Request $request, GatePass $gatePass): RedirectResponse
    {
        $this->authorizeAny('gate_passes.decide');
        $this->assertVisible($gatePass);
        $pass = $this->gatePasses->approve($gatePass, $request->user());

        return back()->with('success', 'Gate Pass approved. OUT machine access is available until '.$pass->expires_at?->format('h:i A').'. '.$pass->employee?->full_name.' has been notified.');
    }

    public function reject(Request $request, GatePass $gatePass): RedirectResponse
    {
        $this->authorizeAny('gate_passes.decide');
        $this->assertVisible($gatePass);
        $pass = $this->gatePasses->reject($gatePass, $request->user(), $request->validate([
            'decision_note' => ['required', 'string', 'min:3', 'max:500'],
        ])['decision_note']);

        return back()->with('success', 'Gate Pass rejected. OUT machine stays closed. '.$pass->employee?->full_name.' has been notified.');
    }

    public function settings(): View
    {
        $this->authorizeAny('gate_passes.manage');

        return view('operations.gate-passes.settings', [
            'settings' => GatePassSetting::forCompany($this->companyId()),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAny('gate_passes.manage');
        $data = $request->validate([
            'max_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'default_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
        ]);

        GatePassSetting::forCompany($this->companyId())->update([
            'allow_self_apply' => $request->boolean('allow_self_apply'),
            'require_parent_approval' => $request->boolean('require_parent_approval'),
            'prevent_duplicate_active' => $request->boolean('prevent_duplicate_active'),
            ...$data,
        ]);

        return redirect()->route('gate-passes.settings')->with('success', 'Gate Pass rules saved.');
    }

    protected function assertVisible(GatePass $gatePass): void
    {
        $this->authorizeAny('gate_passes.view');
        abort_unless($this->activeEmployees()->whereKey($gatePass->employee_id)->exists(), 404);
    }
}
