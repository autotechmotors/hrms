<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Device;
use App\Models\DeviceUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeView();

        return view('operations.devices.index', [
            'devices' => Device::query()->visibleTo($request->user())->where('company_id', $this->companyId())->withCount('users')->orderBy('name')->get(),
            'canEdit' => $request->user()->hasPermission('devices.manage') || $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeDevices();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial' => ['nullable', 'string', 'max:120'],
            'ip_address' => ['nullable', 'ip'],
            'place' => ['nullable', 'string', 'max:120'],
        ]);
        Device::query()->create([
            ...$data,
            'company_id' => $this->companyId(),
            'status' => 'unknown',
        ]);

        return back()->with('success', 'Device saved. Status stays unknown until someone checks it on site. Punches are not imported.');
    }

    public function show(Request $request, Device $device): View
    {
        $this->authorizeView();
        abort_unless((int) $device->company_id === $this->companyId(), 404);

        return view('operations.devices.show', [
            'device' => $device->load('users.employee'),
            'employees' => $this->activeEmployees()->get(),
            'canEdit' => $request->user()->hasPermission('devices.manage') || $request->user()->hasPermission('employees.edit'),
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeDevices();
        abort_unless((int) $device->company_id === $this->companyId(), 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['unknown', 'online', 'offline'])],
        ]);
        $device->update(['status' => $data['status'], 'last_checked_at' => now()]);

        return back()->with('success', 'Device status updated from a manual check. No punches were read.');
    }

    public function enroll(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeDevices();
        abort_unless((int) $device->company_id === $this->companyId(), 404);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'expires_on' => ['nullable', 'date'],
        ]);
        $employee = $this->activeEmployees()->findOrFail($data['employee_id']);
        DeviceUser::query()->updateOrCreate(
            ['device_id' => $device->id, 'employee_id' => $employee->id],
            ['expires_on' => $data['expires_on'] ?: null, 'is_blocked' => false],
        );

        return back()->with('success', 'Employee enrolled on this device.');
    }

    public function block(DeviceUser $enrollment): RedirectResponse
    {
        $this->authorizeDevices();
        $enrollment->load('device');
        abort_unless($enrollment->device && (int) $enrollment->device->company_id === $this->companyId(), 404);
        $enrollment->update(['is_blocked' => ! $enrollment->is_blocked]);

        return back()->with('success', $enrollment->is_blocked ? 'Enrollment blocked.' : 'Enrollment unblocked.');
    }

    public function removeEnrollment(DeviceUser $enrollment): RedirectResponse
    {
        $this->authorizeDevices();
        $enrollment->load('device');
        abort_unless($enrollment->device && (int) $enrollment->device->company_id === $this->companyId(), 404);
        $enrollment->delete();

        return back()->with('success', 'Enrollment removed.');
    }
}
