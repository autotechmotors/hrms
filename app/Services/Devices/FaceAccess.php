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
    ) {}

    /**
     * After an IN, this person is a blocklist user on the out terminal until an
     * approved gate pass covers the moment or the saved minutes before the
     * shift end (and any time after that end). They are a normal user again then.
     */
    public function allowsFace(Employee $employee, Carbon $at): bool
    {
        if ($employee->out_door_closed) {
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
}
