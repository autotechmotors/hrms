<?php

namespace App\Services\Operations;

use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Models\GatePass;
use App\Services\Devices\EmployeePush;
use App\Services\Devices\FaceAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class GatePassAccess
{
    public const OUT_LEAD_MINUTES = 10;

    public function __construct(private readonly WorkHourResolver $hours) {}

    public function grant(GatePass $pass): GatePass
    {
        $pass->loadMissing('employee');
        $employee = $pass->employee;
        if (! $employee || ! $pass->isApproved()) {
            return $pass;
        }

        $devices = $this->gateDevices((int) $pass->company_id);
        $queued = 0;
        foreach ($devices as $device) {
            DeviceUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'employee_id' => $employee->id],
                ['expires_on' => $pass->expires_at?->toDateString(), 'is_blocked' => false],
            );
            DeviceTerminalUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'user_id' => (string) $employee->employee_code],
                ['name' => $employee->full_name, 'employee_id' => $employee->id, 'dismissed_at' => null],
            );
            try {
                app(EmployeePush::class)->rewrite($device, $employee);
                $queued++;
            } catch (RuntimeException) {
                continue;
            }
        }

        $pass->update([
            'machine_status' => $devices->isEmpty() ? 'ready' : ($queued > 0 ? 'queued' : 'ready'),
            'machine_note' => $devices->isEmpty()
                ? 'Approved. No dedicated gate terminal is configured, so OUT is authorized in HRMS until '.$pass->expires_at?->format('h:i A').'.'
                : ($queued > 0
                    ? 'Approved. Queued on '.$queued.' gate terminal(s) until '.$pass->expires_at?->format('h:i A').'.'
                    : 'Approved. OUT is authorized until '.$pass->expires_at?->format('h:i A').'. Terminal queue was busy.'),
        ]);

        if ($employee) {
            app(FaceAccess::class)->sync($employee);
        }

        return $pass->fresh();
    }

    public function revoke(GatePass $pass, string $why = 'Access removed'): GatePass
    {
        $pass->loadMissing('employee');
        $employee = $pass->employee;
        $devices = $this->gateDevices((int) $pass->company_id);

        if ($employee) {
            foreach ($devices as $device) {
                DeviceUser::query()
                    ->where('device_id', $device->id)
                    ->where('employee_id', $employee->id)
                    ->delete();
                DeviceTerminalUser::query()
                    ->where('device_id', $device->id)
                    ->where('user_id', (string) $employee->employee_code)
                    ->delete();
                try {
                    app(EmployeePush::class)->drop(
                        collect([$device]),
                        (string) $employee->employee_code,
                    );
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        $pass->update([
            'machine_status' => 'revoked',
            'machine_note' => $why,
        ]);

        if ($employee) {
            app(FaceAccess::class)->sync($employee);
        }

        return $pass->fresh();
    }

    public function authorized(Employee $employee, Carbon $at): ?GatePass
    {
        $at = $at->copy()->timezone('Asia/Kolkata');

        return GatePass::query()
            ->where('employee_id', $employee->id)
            ->where('status', GatePass::STATUS_APPROVED)
            ->where('expires_at', '>', $at)
            ->orderByDesc('id')
            ->get()
            ->first(function (GatePass $pass) use ($at): bool {
                $time = strlen((string) $pass->out_time) === 5 ? $pass->out_time.':00' : (string) $pass->out_time;
                $start = Carbon::parse($pass->date->toDateString().' '.$time, 'Asia/Kolkata');

                return $at->greaterThanOrEqualTo($start);
            });
    }

    public function openOuting(Employee $employee): ?GatePass
    {
        return GatePass::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('out_at')
            ->whereNull('in_at')
            ->whereIn('status', [GatePass::STATUS_APPROVED, GatePass::STATUS_EXPIRED])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * An OUT punch counts only after an IN, and only for an approved pass
     * or from the minutes before the scheduled end saved in Settings.
     * An earlier scan stays an in and does not fill the out time.
     */
    public function outAllowed(Employee $employee, Carbon $at, ?Carbon $workDate = null): bool
    {
        $at = $at->copy()->timezone('Asia/Kolkata');
        $workDate = ($workDate ?? $this->hours->workDateFor($employee, $at))->copy()->timezone('Asia/Kolkata')->startOfDay();
        $span = $this->hours->attendanceSpan($employee, $workDate);
        $hasIn = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->where('direction', 'in')
            ->where('punched_at', '>=', $span['from'])
            ->where('punched_at', '<', $at)
            ->exists();
        if (! $hasIn) {
            return false;
        }
        if ($this->authorized($employee, $at)) {
            return true;
        }

        return $this->inShiftOutWindow($employee, $at, $workDate);
    }

    public function inShiftOutWindow(Employee $employee, Carbon $at, ?Carbon $workDate = null): bool
    {
        $at = $at->copy()->timezone('Asia/Kolkata');
        $workDate = ($workDate ?? $this->hours->workDateFor($employee, $at))->copy()->timezone('Asia/Kolkata')->startOfDay();
        $window = $this->hours->windowFor($employee, $workDate);
        $start = Carbon::parse($workDate->toDateString().' '.$window['start'], 'Asia/Kolkata');
        $end = Carbon::parse($workDate->toDateString().' '.$window['end'], 'Asia/Kolkata');
        if (($window['overnight'] ?? false) && $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $at->greaterThanOrEqualTo($end->copy()->subMinutes($this->leadMinutes((int) $employee->company_id)));
    }

    public function leadMinutes(int $companyId): int
    {
        $saved = CompanySetting::query()->where('company_id', $companyId)->value('out_inactive_minutes');
        if ($saved === null) {
            return self::OUT_LEAD_MINUTES;
        }

        return max(0, min(240, (int) $saved));
    }

    /**
     * Tag a punch as gate out/return. Returns false when a gate terminal punch is not allowed.
     */
    public function attachPunch(Employee $employee, AttendancePunch $punch, ?Device $device = null): bool
    {
        $device ??= $punch->device;
        $at = $punch->punched_at->copy()->timezone('Asia/Kolkata');
        $open = $this->openOuting($employee);
        $comingBack = $open && $device?->punch_role !== Device::ROLE_OUT && ($device?->readsIn() || $device?->punch_role === null);

        if ($comingBack) {
            $open->update(['in_at' => $at]);
            $punch->update(['gate_pass_id' => $open->id, 'direction' => 'in']);

            return true;
        }

        $pass = $this->authorized($employee, $at);
        $isGate = $device?->isGate() ?? false;
        $hasGate = $this->gateDevices((int) $employee->company_id)->isNotEmpty();

        if ($isGate && ! $pass) {
            return false;
        }

        if (! $pass || $device?->punch_role === Device::ROLE_IN) {
            return true;
        }

        if ($isGate || $device?->punch_role === Device::ROLE_OUT || ! $hasGate) {
            $changes = [];
            if ($pass->out_at === null) {
                $changes['out_at'] = $at;
            }
            if ($pass->in_at !== null) {
                $changes['in_at'] = null;
            }
            if ($changes !== []) {
                $pass->update($changes);
            }
            $punch->update(['gate_pass_id' => $pass->id, 'direction' => 'out']);

            return true;
        }

        return true;
    }

    /**
     * @return Collection<int, Device>
     */
    public function gateDevices(int $companyId)
    {
        return Device::query()
            ->where('company_id', $companyId)
            ->where('gate_kind', Device::KIND_GATE)
            ->orderBy('id')
            ->get();
    }
}
