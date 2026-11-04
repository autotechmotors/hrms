<?php

namespace App\Services\Devices;

use App\Models\Device;
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
     * @return array{faces: int, fingers: int}
     */
    public function copyMissing(): array
    {
        $password = trim((string) config('hikvision.password'));
        if ($password === '') {
            throw new RuntimeException('Set HIKVISION_PASSWORD to the face terminal login, then copy credentials again.');
        }

        $device = $this->device();
        $host = $this->host($device);
        $savedFaces = 0;
        $savedFingers = 0;
        foreach ($this->events->biometrics($host, (string) config('hikvision.username'), $password) as $person) {
            $employee = Employee::query()
                ->where('company_id', $device->company_id)
                ->where('employee_code', $person['code'])
                ->first();
            if (! $employee) {
                continue;
            }
            if ($person['faces'] > 0 && ! $this->stored($employee, 'face')) {
                $photo = $this->events->facePhoto($host, (string) config('hikvision.username'), $password, $person['code']);
                if (is_string($photo) && $photo !== '') {
                    $this->people->storeItem($employee, ['PhotoData' => [$photo]]);
                    $savedFaces++;
                }
            }
            if ($person['fingers'] > 0 && $this->stored($employee, 'finger') < $person['fingers']) {
                foreach ($this->events->fingerprints($host, (string) config('hikvision.username'), $password, $person['code'], $person['fingers']) as $print) {
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
        }

        return ['faces' => $savedFaces, 'fingers' => $savedFingers];
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

    private function host(Device $device): string
    {
        $configured = trim((string) config('hikvision.host'));
        $port = is_numeric(config('hikvision.port')) ? (int) config('hikvision.port') : 0;
        if ($configured !== '' && $port > 0) {
            return $port === 80 ? $configured : $configured.':'.$port;
        }

        return $configured !== '' ? $configured : trim((string) $device->ip_address);
    }
}
