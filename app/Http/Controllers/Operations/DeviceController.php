<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\DeviceUser;
use App\Services\Devices\EmployeePush;
use App\Services\Devices\LocalPcServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class DeviceController extends Controller
{
    use UsesWorkspace;

    public function index(Request $request): View
    {
        $this->authorizeDevices();

        $devices = Device::query()->visibleTo($request->user())->where('company_id', $this->companyId())->withCount('users')->orderBy('name')->get();

        $pc = app(LocalPcServer::class);

        return view('operations.devices.index', [
            'devices' => $devices,
            'canEdit' => $request->user()->hasPermission('devices.manage'),
            'enrolledCount' => (int) $devices->sum('users_count'),
            'localPc' => $pc->status(),
        ]);
    }

    public function enableLocalPc(LocalPcServer $pc): RedirectResponse
    {
        $this->authorizeDevices();
        $result = $pc->enableAutostart();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function disableLocalPc(LocalPcServer $pc): RedirectResponse
    {
        $this->authorizeDevices();
        $result = $pc->disableAutostart();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function startLocalPc(LocalPcServer $pc): RedirectResponse
    {
        $this->authorizeDevices();
        $result = $pc->startNow();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function stopLocalPc(LocalPcServer $pc): RedirectResponse
    {
        $this->authorizeDevices();
        $result = $pc->stopNow();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeDevices();
        Device::query()->create([
            ...$this->devicePayload($request, true),
            'company_id' => $this->companyId(),
            'status' => 'unknown',
        ]);

        return back()->with('success', 'Device added.');
    }

    public function show(Request $request, Device $device): View
    {
        $this->authorizeDevices();
        abort_unless((int) $device->company_id === $this->companyId(), 404);

        return view('operations.devices.show', [
            'device' => $device->load(['users.employee', 'terminalUsers.employee']),
            'employees' => $this->activeEmployees()->orderBy('employee_code')->get(),
            'canEdit' => $request->user()->hasPermission('devices.manage'),
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeDevices();
        abort_unless((int) $device->company_id === $this->companyId(), 404);
        $device->update([
            ...$this->devicePayload($request, false),
            'last_checked_at' => now(),
        ]);

        return back()->with('success', 'Device updated.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorizeDevices();
        abort_unless((int) $device->company_id === $this->companyId(), 404);
        $device->load('users.employee');
        $people = app(EmployeePush::class);
        $queued = true;
        foreach ($device->users as $enrollment) {
            if (! $enrollment->employee) {
                continue;
            }
            try {
                $people->drop(collect([$device]), (string) $enrollment->employee->employee_code);
            } catch (\Throwable) {
                $queued = false;
            }
        }
        $device->delete();

        return redirect()
            ->route('devices.index')
            ->with($queued ? 'success' : 'warning', $queued
                ? 'Device removed.'
                : 'Device removed. A terminal delete could not be queued for every enrolled person.');
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
            ['expires_on' => $data['expires_on'] ?? null, 'is_blocked' => false],
        );
        DeviceTerminalUser::query()->updateOrCreate(
            ['device_id' => $device->id, 'user_id' => (string) $employee->employee_code],
            ['name' => $employee->full_name, 'employee_id' => $employee->id, 'dismissed_at' => null],
        );

        try {
            app(EmployeePush::class)->rewrite($device, $employee);
        } catch (RuntimeException $exception) {
            return back()->with('success', 'Enrollment saved in HRMS, but the terminal command was not queued: '.$exception->getMessage());
        }

        return back()->with('success', $employee->full_name.' is enrolled. The terminal will add user '.$employee->employee_code.'.');
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
        $employee = $enrollment->employee;
        $device = $enrollment->device;
        $enrollment->delete();

        if ($employee) {
            DeviceTerminalUser::query()
                ->where('device_id', $device->id)
                ->where('user_id', $employee->employee_code)
                ->delete();
            try {
                app(EmployeePush::class)->drop(collect([$device]), (string) $employee->employee_code);
            } catch (\Throwable $exception) {
                return back()->with('success', 'Enrollment removed in HRMS, but the terminal delete was not queued: '.$exception->getMessage());
            }
        }

        return back()->with('success', 'Enrollment removed. The terminal will delete this user on its next poll.');
    }

    public function updateTerminalUser(Request $request, DeviceTerminalUser $person): RedirectResponse
    {
        $this->authorizeDevices();
        $person->load('device');
        abort_unless($person->device && (int) $person->device->company_id === $this->companyId(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $person->update(['name' => $data['name'], 'synced_at' => null]);

        try {
            $employee = $person->employee;
            if (! $employee) {
                throw new RuntimeException($person->device->name.' needs an HRMS employee linked before the name can sync.');
            }
            app(EmployeePush::class)->rewrite($person->device, $employee, (string) $person->user_id, $data['name']);
        } catch (RuntimeException $exception) {
            return back()->with('error', 'Name saved here, but the terminal command was not queued: '.$exception->getMessage());
        }

        return back()->with('success', 'Name update queued. The terminal will apply '.$data['name'].' on its next poll.');
    }

    public function destroyTerminalUser(DeviceTerminalUser $person): RedirectResponse
    {
        $this->authorizeDevices();
        $person->load('device');
        abort_unless($person->device && (int) $person->device->company_id === $this->companyId(), 404);
        $userId = $person->user_id;
        $device = $person->device;
        if ($person->employee_id) {
            DeviceUser::query()
                ->where('device_id', $device->id)
                ->where('employee_id', $person->employee_id)
                ->delete();
        }
        $person->delete();

        try {
            app(EmployeePush::class)->drop(collect([$device]), (string) $userId);
        } catch (RuntimeException $exception) {
            return back()->with('success', 'Person removed here, but the terminal delete was not queued: '.$exception->getMessage());
        }

        return back()->with('success', 'Person removed. The terminal will delete user '.$userId.' on its next poll.');
    }

    private function devicePayload(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial' => ['nullable', 'string', 'max:120'],
            'register_id' => [$creating ? 'nullable' : 'required', 'string', 'max:64'],
            'ip_address' => ['nullable', 'ip'],
            'username' => ['nullable', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:64'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'place' => ['nullable', 'string', 'max:120'],
            'gate_kind' => ['nullable', Rule::in(['attendance', 'gate'])],
            'status' => [$creating ? 'nullable' : 'required', Rule::in(['unknown', 'online', 'offline'])],
        ]);
        $data['gate_kind'] = $data['gate_kind'] ?? Device::KIND_ATTENDANCE;
        $data['register_id'] = trim((string) ($data['register_id'] ?? '')) ?: config('devices.register_id');
        $data['username'] = trim((string) ($data['username'] ?? '')) ?: null;
        $data['port'] = ($data['port'] ?? null) ?: null;
        if (trim((string) ($data['password'] ?? '')) === '') {
            unset($data['password']);
        }
        unset($data['status']);
        if (! $creating) {
            $data['status'] = $request->string('status')->toString();
        }

        return $data;
    }
}
