<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Support\StoredImage;
use Illuminate\Support\Collection;
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

    /**
     * Put the face model that already matches onto the other terminals.
     * The IN terminal is the source when it has a model. Fingerprints are not sent.
     *
     * @param  iterable<Device>  $devices
     */
    public function shareMatchingFace(iterable $devices, string $code, string $name): int
    {
        $code = trim($code);
        $name = trim($name) !== '' ? trim($name) : $code;
        if ($code === '') {
            return 0;
        }

        /** @var Collection<int, Device> $pool */
        $pool = collect($devices)
            ->filter(fn ($device) => $device instanceof Device && $device->isHikvision())
            ->unique('id')
            ->values();
        $origin = null;
        $jpeg = null;
        $model = '';
        foreach ($pool->sortBy(fn (Device $device) => $device->punch_role === Device::ROLE_IN ? 0 : 1)->values() as $device) {
            $found = $this->events->faceTemplate(
                $device->terminalHost(),
                $device->terminalUsername(),
                $device->terminalPassword(),
                $code,
            );
            if ($found['jpeg'] !== null && $found['model'] !== '') {
                $origin = $device;
                $jpeg = $found['jpeg'];
                $model = $found['model'];
                break;
            }
        }
        if (! $origin instanceof Device || ! is_string($jpeg) || $model === '') {
            return 0;
        }

        $enroll = app(HikvisionEnroll::class);
        $written = 0;
        foreach ($pool as $device) {
            if ($device->id === $origin->id) {
                continue;
            }
            $current = $this->events->faceTemplate(
                $device->terminalHost(),
                $device->terminalUsername(),
                $device->terminalPassword(),
                $code,
            );
            if (! $this->sameFaceEngine($model, $current['model'])) {
                continue;
            }
            $enroll->putSameFace($device, $code, $name, $jpeg, $model);
            $written++;
        }

        return $written;
    }

    /**
     * Copy the face model that already matches on one terminal onto another.
     * The picture and the model are written together, and the target is not rebuilt.
     *
     * @return array{restored: int, missing: int, failed: int, total: int}
     */
    public function mirrorFaces(Device $from, Device $to): array
    {
        $enroll = app(HikvisionEnroll::class);
        $people = array_values(array_filter(
            $this->listing($from),
            fn (array $person): bool => trim((string) $person['code']) !== '' && (int) $person['faces'] >= 1,
        ));
        $restored = 0;
        $missing = 0;
        $failed = 0;
        foreach ($people as $person) {
            $code = trim((string) $person['code']);
            if ($code === '' || (int) $person['faces'] < 1) {
                continue;
            }
            $template = $this->events->faceTemplate(
                $from->terminalHost(),
                $from->terminalUsername(),
                $from->terminalPassword(),
                $code,
            );
            $jpeg = $template['jpeg'];
            $model = $template['model'];
            if ($jpeg === null || $model === '') {
                $missing++;

                continue;
            }
            $current = $this->events->faceTemplate(
                $to->terminalHost(),
                $to->terminalUsername(),
                $to->terminalPassword(),
                $code,
            );
            if (! $this->sameFaceEngine($model, $current['model'])) {
                $missing++;

                continue;
            }
            try {
                $enroll->putSameFace($to, $code, trim((string) ($person['name'] ?: $code)), $jpeg, $model);
                $restored++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return [
            'restored' => $restored,
            'missing' => $missing,
            'failed' => $failed,
            'total' => count($people),
        ];
    }

    private function sameFaceEngine(string $left, string $right): bool
    {
        $from = $this->faceEngine($left);
        $to = $this->faceEngine($right);
        if ($from === '' || $to === '') {
            return true;
        }

        return $from === $to;
    }

    private function faceEngine(string $model): string
    {
        $raw = base64_decode($model, true);
        if (! is_string($raw) || preg_match('/^FR(\d)/', $raw, $match) !== 1) {
            return '';
        }

        return $match[1];
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
