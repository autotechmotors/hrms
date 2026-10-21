<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Operations\Concerns\UsesWorkspace;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\DeviceUser;
use App\Services\Devices\DahuaPush;
use App\Services\Devices\EmployeePush;
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
        $this->authorizeView();

        $devices = Device::query()->visibleTo($request->user())->where('company_id', $this->companyId())->withCount('users')->orderBy('name')->get();

        return view('operations.devices.index', [
            'devices' => $devices,
            'canEdit' => $request->user()->hasPermission('devices.manage') || $request->user()->hasPermission('employees.edit'),
            'listenerOpen' => app(DahuaPush::class)->listenerOpen(),
            'enrolledCount' => (int) $devices->sum('users_count'),
        ]);
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
        $this->authorizeView();
        abort_unless((int) $device->company_id === $this->companyId(), 404);

        $push = app(DahuaPush::class);
        $imported = $push->importInbox($device);

        return view('operations.devices.show', [
            'device' => $device->load(['users.employee', 'terminalUsers.employee']),
            'imported' => $imported,
            'employees' => $this->activeEmployees()->orderBy('employee_code')->get(),
            'canEdit' => $request->user()->hasPermission('devices.manage') || $request->user()->hasPermission('employees.edit'),
            'pushHost' => config('dahua.host'),
            'pushPort' => config('dahua.port'),
            'listenerOpen' => $push->listenerOpen(),
            'connection' => $push->connection(),
            'lastResult' => $push->lastResult(),
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
        $push = app(DahuaPush::class);
        $queued = true;
        foreach ($device->users as $enrollment) {
            if (! $enrollment->employee) {
                continue;
            }
            try {
                $push->queueDeletePerson($device, $enrollment->employee);
            } catch (RuntimeException) {
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
        app(DahuaPush::class)->rememberPerson($device, $employee->employee_code, $employee->full_name, $employee->id);

        try {
            app(DahuaPush::class)->queueAddPerson($device, $employee, $data['expires_on'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->with('success', 'Enrollment saved in HRMS, but the terminal command was not queued: '.$exception->getMessage());
        }

        return back()->with('success', $employee->full_name.' is enrolled. The terminal will add user '.$employee->employee_code.' on its next poll.');
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
                app(DahuaPush::class)->queueDeletePerson($device, $employee);
            } catch (RuntimeException $exception) {
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
            if ($employee) {
                app(EmployeePush::class)->rewrite($person->device, $employee, (string) $person->user_id, $data['name']);
            } else {
                app(DahuaPush::class)->queueUpsertPerson($person->device, $person->user_id, $data['name']);
            }
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
            app(DahuaPush::class)->queueDeleteUser($device, $userId);
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
            'place' => ['nullable', 'string', 'max:120'],
            'status' => [$creating ? 'nullable' : 'required', Rule::in(['unknown', 'online', 'offline'])],
        ]);
        $data['register_id'] = trim((string) ($data['register_id'] ?? '')) ?: config('dahua.register_id');
        unset($data['status']);
        if (! $creating) {
            $data['status'] = $request->string('status')->toString();
        }

        return $data;
    }
}
