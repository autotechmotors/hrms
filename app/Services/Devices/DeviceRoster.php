<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use Throwable;

class DeviceRoster
{
    public function __construct(
        private readonly HikvisionCredentials $credentials,
        private readonly EmployeePush $push,
    ) {}

    /**
     * Read this terminal into HRMS, then copy those people onto every other terminal.
     *
     * @return array{added: int, faces: int, fingers: int, copied: int, failed: int}
     */
    public function sync(Device $source): array
    {
        $imported = $this->credentials->import($source, true);
        $targets = Device::query()
            ->where('company_id', $source->company_id)
            ->where('id', '!=', $source->id)
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision())
            ->values();
        $copied = 0;
        $failed = 0;
        $people = DeviceTerminalUser::query()
            ->where('device_id', $source->id)
            ->whereNull('dismissed_at')
            ->whereNotNull('employee_id')
            ->with('employee')
            ->get();

        foreach ($people as $person) {
            $employee = $person->employee;
            if (! $employee instanceof Employee) {
                continue;
            }
            foreach ($targets as $target) {
                try {
                    $this->push->rewrite($target, $employee, (string) $person->user_id, (string) $employee->full_name);
                    DeviceTerminalUser::query()->updateOrCreate(
                        ['device_id' => $target->id, 'user_id' => (string) $person->user_id],
                        ['name' => (string) $employee->full_name, 'employee_id' => $employee->id, 'dismissed_at' => null],
                    );
                    $copied++;
                } catch (Throwable) {
                    $failed++;
                }
            }
        }

        return [
            'added' => $imported['added'],
            'faces' => $imported['faces'],
            'fingers' => $imported['fingers'],
            'copied' => $copied,
            'failed' => $failed,
        ];
    }
}
