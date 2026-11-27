<?php

namespace App\Services\Devices;

use App\Models\AttendanceDay;
use App\Models\Device;
use App\Models\Employee;
use App\Services\Operations\GatePassAccess;
use App\Services\Operations\WorkHourResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class FaceAccess
{
    public function __construct(
        private readonly WorkHourResolver $hours,
        private readonly GatePassAccess $passes,
        private readonly EmployeePush $push,
        private readonly HikvisionEvents $events,
    ) {}

    /**
     * After an IN, this person is a blocklist user on the out terminal until an
     * approved gate pass covers the moment or the saved minutes before the
     * shift end (and any time after that end). They are a normal user again then.
     */
    public function allowsFace(Employee $employee, Carbon $at): bool
    {
        if ($employee->out_door_forced) {
            return false;
        }

        $at = $at->copy()->timezone('Asia/Kolkata');
        $workDate = $this->hours->workDateFor($employee, $at);
        $day = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->first();
        if (! $day || $day->punch_in === null || $day->punch_in === '' || $day->punch_out) {
            return true;
        }
        if ($this->passes->authorized($employee, $at)) {
            return true;
        }

        $window = $this->hours->windowFor($employee, $workDate);
        $start = Carbon::parse($workDate->toDateString().' '.$window['start'], 'Asia/Kolkata');
        $end = Carbon::parse($workDate->toDateString().' '.$window['end'], 'Asia/Kolkata');
        if (($window['overnight'] ?? false) && $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $at->greaterThanOrEqualTo($end->copy()->subMinutes($this->passes->leadMinutes((int) $employee->company_id)));
    }

    public function sync(Employee $employee, ?Carbon $at = null): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $allow = $this->allowsFace($employee, $at ?? now()->timezone('Asia/Kolkata'));
        $devices = $this->gateTerminals((int) $employee->company_id);

        foreach ($devices as $device) {
            $key = 'face-pic.'.$device->id.'.'.$employee->id;
            $next = $allow ? '1' : '0';
            if (Cache::get($key) === $next) {
                continue;
            }
            $this->push->faceAccess($employee, $device, $allow);
            Cache::put($key, $next, now()->addDays(2));
        }

        $closed = ! $allow;
        if ((bool) $employee->out_door_closed !== $closed) {
            $employee->update(['out_door_closed' => $closed]);
        }
    }

    /**
     * Suspend blocks this person on the IN terminal only. SetUp keeps the face and the finger.
     */
    public function syncIn(Employee $employee): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $allow = ! $employee->in_suspended;
        foreach ($this->inTerminals((int) $employee->company_id) as $device) {
            $key = 'face-in.'.$device->id.'.'.$employee->id;
            $next = $allow ? '1' : '0';
            if (Cache::get($key) === $next) {
                continue;
            }
            $this->push->faceAccess($employee, $device, $allow);
            Cache::put($key, $next, now()->addDays(2));
        }
    }

    /**
     * The OUT attendance terminal is blocked after IN, and while Out Door Closed is on.
     * A gate terminal follows the same out rule. Suspend uses the IN terminal instead.
     *
     * @return \Illuminate\Support\Collection<int, Device>
     */
    public function gateTerminals(int $companyId): \Illuminate\Support\Collection
    {
        return Device::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision() && ($device->isGate() || $device->punch_role === Device::ROLE_OUT))
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Device>
     */
    public function inTerminals(int $companyId): \Illuminate\Support\Collection
    {
        return Device::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision() && $device->punch_role === Device::ROLE_IN)
            ->values();
    }

    public function syncOpen(): int
    {
        $this->reflectDevices();
        $ids = AttendanceDay::query()
            ->whereDate('work_date', '>=', now()->timezone('Asia/Kolkata')->subDay()->toDateString())
            ->whereNotNull('punch_in')
            ->whereNull('punch_out')
            ->pluck('employee_id')
            ->unique()
            ->filter();
        $count = 0;
        Employee::query()->whereIn('id', $ids)->orderBy('id')->each(function (Employee $employee) use (&$count): void {
            $this->sync($employee);
            $count++;
        });

        Employee::query()
            ->where('out_door_closed', true)
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $ids))
            ->orderBy('id')
            ->each(function (Employee $employee) use (&$count): void {
                $this->sync($employee);
                $count++;
            });

        Employee::query()
            ->where('in_suspended', true)
            ->orderBy('id')
            ->each(function (Employee $employee): void {
                $this->syncIn($employee);
            });

        return $count;
    }

    /**
     * Copy each door's block list onto the employee switches.
     * A person blocked on OUT while the shift would allow them stays closed.
     */
    public function reflectDevices(): int
    {
        if (app()->runningUnitTests()) {
            return 0;
        }

        $changed = 0;
        $companyIds = Device::query()->pluck('company_id')->unique()->filter();
        foreach ($companyIds as $companyId) {
            foreach ($this->inTerminals((int) $companyId) as $device) {
                $types = $this->readTypes($device);
                if ($types === null) {
                    continue;
                }
                $changed += $this->matchSwitches((int) $companyId, $types, 'in');
                $this->rememberDoor($device, $types, 'in');
            }
            foreach ($this->gateTerminals((int) $companyId) as $device) {
                if ($device->punch_role !== Device::ROLE_OUT) {
                    continue;
                }
                $types = $this->readTypes($device);
                if ($types === null) {
                    continue;
                }
                $changed += $this->matchSwitches((int) $companyId, $types, 'out');
                $this->rememberDoor($device, $types, 'out');
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, string>  $types
     */
    public function matchSwitches(int $companyId, array $types, string $door): int
    {
        $changed = 0;
        Employee::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->each(function (Employee $employee) use ($types, $door, &$changed): void {
                $code = trim((string) $employee->employee_code);
                if ($code === '' || ! array_key_exists($code, $types)) {
                    return;
                }
                $blocked = $types[$code] === 'blackList';
                if ($door === 'in') {
                    if ((bool) $employee->in_suspended === $blocked) {
                        return;
                    }
                    $employee->update(['in_suspended' => $blocked]);
                    $changed++;

                    return;
                }

                $forced = $blocked && $this->scheduleAllows($employee, now()->timezone('Asia/Kolkata'));
                $updates = [];
                if ((bool) $employee->out_door_forced !== $forced) {
                    $updates['out_door_forced'] = $forced;
                }
                if ((bool) $employee->out_door_closed !== $blocked) {
                    $updates['out_door_closed'] = $blocked;
                }
                if ($updates === []) {
                    return;
                }
                $employee->update($updates);
                $changed++;
            });

        return $changed;
    }

    /**
     * @return array<string, string>|null
     */
    private function readTypes(Device $device): ?array
    {
        try {
            return $this->events->userTypes(
                $device->terminalHost(),
                $device->terminalUsername(),
                $device->terminalPassword(),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $types
     */
    private function rememberDoor(Device $device, array $types, string $door): void
    {
        $codes = Employee::query()
            ->where('company_id', $device->company_id)
            ->pluck('id', 'employee_code');
        foreach ($types as $code => $type) {
            $id = $codes[$code] ?? null;
            if (! $id) {
                continue;
            }
            $prefix = $door === 'in' ? 'face-in.' : 'face-pic.';
            Cache::put($prefix.$device->id.'.'.$id, $type === 'blackList' ? '0' : '1', now()->addDays(2));
        }
    }

    private function scheduleAllows(Employee $employee, Carbon $at): bool
    {
        $forced = (bool) $employee->out_door_forced;
        if ($forced) {
            $employee->out_door_forced = false;
        }
        $allow = $this->allowsFace($employee, $at);
        if ($forced) {
            $employee->out_door_forced = true;
        }

        return $allow;
    }
}
