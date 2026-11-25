<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use RuntimeException;

class HikvisionCredentials
{
    public function __construct(
        private readonly HikvisionEvents $events,
        private readonly DevicePeople $people,
    ) {}

    /**
     * @return array{added: int, faces: int, fingers: int}
     */
    public function copyMissing(): array
    {
        return $this->import($this->device());
    }

    /**
     * @return array{added: int, faces: int, fingers: int}
     */
    public function import(Device $device, bool $preferTerminal = false): array
    {
        $added = 0;
        $savedFaces = 0;
        $savedFingers = 0;
        foreach ($this->listing($device) as $person) {
            $row = $this->take($device, $person, $preferTerminal);
            $added += $row['added'];
            $savedFaces += $row['faces'];
            $savedFingers += $row['fingers'];
        }

        return ['added' => $added, 'faces' => $savedFaces, 'fingers' => $savedFingers];
    }

    /**
     * @return list<array{code: string, name: string, faces: int, fingers: int}>
     */
    public function listing(Device $device): array
    {
        $password = $device->terminalPassword();
        if ($password === '') {
            throw new RuntimeException('Set the face terminal password in Devices before copying credentials.');
        }

        return $this->events->biometrics($device->terminalHost(), $device->terminalUsername(), $password);
    }

    /**
     * @param  array{code: string, name: string, faces: int, fingers: int}  $person
     * @return array{added: int, faces: int, fingers: int}
     */
    public function take(Device $device, array $person, bool $preferTerminal = false): array
    {
        $password = $device->terminalPassword();
        if ($password === '') {
            throw new RuntimeException('Set the face terminal password in Devices before copying credentials.');
        }
        if ($this->dismissed($device, $person['code'])) {
            return ['added' => 0, 'faces' => 0, 'fingers' => 0];
        }

        $host = $device->terminalHost();
        $added = 0;
        $savedFaces = 0;
        $savedFingers = 0;
        $employee = Employee::query()
            ->where('company_id', $device->company_id)
            ->where('employee_code', $person['code'])
            ->first();
        if (! $employee) {
            $employee = $this->people->ensure($device, $person['code'], $person['name']);
            if (! $employee) {
                return ['added' => 0, 'faces' => 0, 'fingers' => 0];
            }
            $added++;
        }
        DeviceTerminalUser::query()->updateOrCreate(
            ['device_id' => $device->id, 'user_id' => $person['code']],
            ['name' => $person['name'], 'employee_id' => $employee->id, 'dismissed_at' => null],
        );
        if ($person['faces'] > 0 && ($preferTerminal || ! $this->stored($employee, 'face'))) {
            $photo = $this->events->facePhoto($host, $device->terminalUsername(), $password, $person['code']);
            if (is_string($photo) && $photo !== '') {
                $this->people->storeItem($employee, ['PhotoData' => [$photo]]);
                $savedFaces++;
            }
        }
        if ($person['fingers'] > 0 && $this->stored($employee, 'finger') < $person['fingers']) {
            foreach ($this->events->fingerprints($host, $device->terminalUsername(), $password, $person['code'], $person['fingers']) as $print) {
                $slot = (string) $print['id'];
                $exists = EmployeeCredential::query()
                    ->where('employee_id', $employee->id)
                    ->where('kind', 'finger')
                    ->where('slot', $slot)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $this->people->storeItem($employee, ['FingerPrints' => [[
                    'FingerprintID' => $print['id'],
                    'FingerprintData' => $print['data'],
                ]]]);
                $savedFingers++;
            }
        }

        return ['added' => $added, 'faces' => $savedFaces, 'fingers' => $savedFingers];
    }

    private function dismissed(Device $device, string $code): bool
    {
        return DeviceTerminalUser::query()
            ->where('device_id', $device->id)
            ->where('user_id', $code)
            ->whereNotNull('dismissed_at')
            ->exists();
    }

    private function stored(Employee $employee, string $kind): int
    {
        return EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->where('kind', $kind)
            ->count();
    }

    private function device(): Device
    {
        $registerId = trim((string) config('hikvision.register_id'));
        $device = Device::query()->get()->first(function (Device $row) use ($registerId): bool {
            return $row->isHikvision() && ($registerId === '' || strcasecmp((string) $row->register_id, $registerId) === 0);
        });
        if ($device) {
            return $device;
        }

        throw new RuntimeException('Add the Hikvision face terminal before copying faces and fingers.');
    }

}
