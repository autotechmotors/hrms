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

            $exists = Device::query()->get()->contains(
                fn (Device $row) => strcasecmp((string) $row->register_id, $registerId) === 0,
            );
            if ($exists) {
                continue;
            }

            $port = is_numeric($terminal['port'] ?? null) ? (int) $terminal['port'] : 0;

            Device::query()->create([
                'company_id' => $companyId,
                'name' => $role === 'out' ? 'Hikvision-(OUT)' : 'Hikvision-(IN)',
                'model' => 'Hikvision',
                'register_id' => $registerId,
                'ip_address' => $host,
                'username' => trim((string) ($terminal['username'] ?? 'admin')) ?: 'admin',
                'password' => $password,
                'punch_role' => $role === 'out' ? Device::ROLE_OUT : Device::ROLE_IN,
                'gate_kind' => Device::KIND_ATTENDANCE,
                'port' => $port > 0 ? $port : null,
                'status' => 'offline',
            ]);
        }
    }
}
