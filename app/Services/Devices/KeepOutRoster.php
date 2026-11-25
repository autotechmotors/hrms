<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KeepOutRoster
{
    public function __construct(private readonly EmployeePush $push) {}

    /**
     * Keep the people enrolled on the OUT terminal. Everyone else is removed
     * from HRMS and from the other terminals. Faces and fingers of the kept
     * people stay.
     *
     * @return array{kept: int, removed: int, device_removed: int, duplicates: list<string>, names: list<string>}
     */
    public function run(bool $apply): array
    {
        $out = $this->outDevice();
        $codes = DeviceTerminalUser::query()
            ->where('device_id', $out->id)
            ->whereNull('dismissed_at')
            ->pluck('user_id')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        $keep = Employee::query()
            ->where('company_id', $out->company_id)
            ->whereIn('employee_code', $codes->all())
            ->pluck('id');
        $remove = Employee::query()
            ->where('company_id', $out->company_id)
            ->whereNotIn('id', $keep->all() ?: [0])
            ->pluck('id');

        $duplicates = Employee::query()
            ->where('company_id', $out->company_id)
            ->select('employee_code', DB::raw('count(*) as copies'))
            ->groupBy('employee_code')
            ->having('copies', '>', 1)
            ->pluck('employee_code')
            ->map(fn ($code) => (string) $code)
            ->all();

        $names = Employee::query()
            ->whereIn('id', $keep->all() ?: [0])
            ->get(['first_name', 'last_name', 'employee_code'])
            ->groupBy(fn (Employee $employee) => mb_strtolower(trim($employee->full_name)))
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group) => $group->pluck('employee_code')->implode(', '))
            ->values()
            ->all();

        $others = Device::query()
            ->where('company_id', $out->company_id)
            ->where('id', '!=', $out->id)
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision())
            ->values();
        $extraOnDevices = DeviceTerminalUser::query()
            ->whereIn('device_id', $others->pluck('id')->all() ?: [0])
            ->whereNotIn('user_id', $codes->all() ?: ['__none__'])
            ->get(['id', 'user_id']);

        if ($apply && $codes->isEmpty()) {
            throw new \RuntimeException('The OUT terminal has no people, so nobody was deleted.');
        }

        if ($apply) {
            if ($remove->isNotEmpty()) {
                Employee::query()->whereIn('reporting_manager_id', $remove->all())->update(['reporting_manager_id' => null]);
                foreach (['payment_batch_lines', 'payroll_items', 'employee_documents', 'employment_events', 'employee_exits', 'employee_bank_accounts'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('employee_id', $remove->all())->delete();
                    }
                }
                Employee::query()->whereIn('id', $remove->all())->delete();
            }

            foreach ($extraOnDevices->pluck('user_id')->unique() as $userId) {
                $this->push->drop($others, (string) $userId);
            }
            if ($extraOnDevices->isNotEmpty()) {
                DeviceTerminalUser::query()->whereIn('id', $extraOnDevices->pluck('id')->all())->delete();
            }
        }

        return [
            'kept' => $codes->count(),
            'removed' => $remove->count(),
            'device_removed' => $extraOnDevices->count(),
            'faces' => EmployeeCredential::query()->whereIn('employee_id', $keep->all() ?: [0])->where('kind', 'face')->count(),
            'fingers' => EmployeeCredential::query()->whereIn('employee_id', $keep->all() ?: [0])->where('kind', 'finger')->count(),
            'duplicates' => $duplicates,
            'names' => $names,
        ];
    }

    private function outDevice(): Device
    {
        $device = Device::query()->get()->first(function (Device $device): bool {
            return $device->isHikvision() && $device->punch_role === Device::ROLE_OUT;
        });
        if ($device instanceof Device) {
            return $device;
        }

        throw new \RuntimeException('Add the OUT attendance terminal before keeping only its people.');
    }
}
