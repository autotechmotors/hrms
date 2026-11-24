<?php

namespace App\Services\Devices;

use App\Models\Company;
use App\Models\Device;

class HikvisionTerminals
{
    public function ensure(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $companyId = (int) (Device::query()->value('company_id') ?: Company::query()->value('id'));
        if ($companyId === 0) {
            return;
        }

        foreach (config('hikvision.terminals', []) as $role => $terminal) {
            if (! is_array($terminal)) {
                continue;
            }
            $host = trim((string) ($terminal['host'] ?? ''));
            $registerId = trim((string) ($terminal['register_id'] ?? ''));
            $password = trim((string) ($terminal['password'] ?? ''));
            if ($host === '' || $registerId === '' || $password === '' || ! str_starts_with(strtoupper($registerId), 'HIK')) {
                continue;
            }

            $device = Device::query()->get()->first(function (Device $row) use ($registerId, $host): bool {
                return strcasecmp((string) $row->register_id, $registerId) === 0
                    || trim((string) $row->ip_address) === $host;
            });
            $values = [
                'ip_address' => $host,
                'username' => trim((string) ($terminal['username'] ?? 'admin')) ?: 'admin',
                'password' => $password,
                'punch_role' => $role === 'out' ? Device::ROLE_OUT : Device::ROLE_IN,
                'gate_kind' => Device::KIND_ATTENDANCE,
                'port' => 80,
            ];
            if ($device) {
                if (strcasecmp((string) $device->register_id, $registerId) !== 0 && trim((string) $device->register_id) === '') {
                    $values['register_id'] = $registerId;
                }
                $device->update($values);

                continue;
            }

            Device::query()->create([
                'company_id' => $companyId,
                'name' => $role === 'out' ? 'Hikvision-(OUT)' : 'Hikvision-(IN)',
                'model' => 'Hikvision',
                'register_id' => $registerId,
                'status' => 'offline',
                ...$values,
            ]);
        }
    }
}
