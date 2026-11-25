<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Services\Devices\DevicePeople;
use App\Services\Devices\DeviceSync;
use App\Services\Devices\HikvisionEvents;
use App\Services\Devices\HikvisionJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HikvisionEventController extends Controller
{
    public function __invoke(Request $request, string $token, HikvisionEvents $events, DeviceSync $sync): JsonResponse|Response
    {
        $expected = (string) config('devices.forward_token');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(401);
        }

        if ($request->isMethod('GET')) {
            return response('Hikvision event receiver is running.', 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $scan = $events->fromPush($request->getContent());
        if ($scan === null) {
            return response()->json(['saved' => 0, 'ignored' => true]);
        }

        $device = $this->device($scan['ip'], (string) ($scan['device_name'] ?? ''), (string) ($scan['attendance'] ?? ''));
        if (! $device) {
            return response()->json([
                'saved' => 0,
                'pending' => true,
                'message' => 'Device is not on this server.',
            ]);
        }

        $result = $sync->receive($device, [
            'RegisterID' => (string) $device->register_id,
            'NotifyInfo' => ['Events' => [[
                'Code' => 'AccessControl',
                'Data' => [
                    'ErrorCode' => 0,
                    'Type' => $scan['type'],
                    'RealUTC' => $scan['utc'],
                    'UserID' => $scan['user_id'],
                    'UserName' => $scan['name'],
                    'SN' => (string) $device->register_id,
                ],
            ]]],
        ], $scan['serial'], $scan['image']);

        return response()->json($result);
    }

    public function people(Request $request, string $token, DevicePeople $people): JsonResponse
    {
        $expected = (string) config('devices.forward_token');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(401);
        }

        $rows = $request->validate([
            'people' => ['required', 'array', 'max:500'],
            'people.*.code' => ['required', 'string', 'max:50'],
            'people.*.name' => ['nullable', 'string', 'max:120'],
        ]);
        $device = $this->device('');
        if (! $device) {
            return response()->json(['added' => 0, 'message' => 'Device is not on this server.'], 422);
        }

        $added = 0;
        $existing = 0;
        foreach ($rows['people'] as $row) {
            $code = trim($row['code']);
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '') {
                continue;
            }
            $already = Employee::query()
                ->where('company_id', $device->company_id)
                ->where('employee_code', $code)
                ->exists();
            $employee = $people->ensure($device, $code, $name !== '' ? $name : $code);
            if (! $employee) {
                continue;
            }
            DeviceTerminalUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'user_id' => $code],
                ['name' => $name !== '' ? $name : $code, 'employee_id' => $employee->id, 'dismissed_at' => null],
            );
            $already ? $existing++ : $added++;
        }

        return response()->json(['added' => $added, 'existing' => $existing]);
    }

    public function nextJob(string $token, HikvisionJobs $jobs): JsonResponse
    {
        $this->token($token);
        $job = $jobs->claim();

        return response()->json(['job' => $job]);
    }

    public function finishJob(Request $request, string $token, string $id, HikvisionJobs $jobs): JsonResponse
    {
        $this->token($token);
        $result = $request->validate([
            'ok' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
            'face' => ['nullable', 'string'],
            'finger' => ['nullable', 'string'],
            'slot' => ['nullable', 'integer'],
        ]);

        return response()->json($jobs->finish($id, $result));
    }

    private function token(string $token): void
    {
        $expected = (string) config('devices.forward_token');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(401);
        }
    }

    private function device(string $ip, string $name = '', string $attendance = ''): ?Device
    {
        $devices = Device::query()->get()->filter(fn (Device $device) => $device->isHikvision())->values();
        $match = null;
        if ($ip !== '') {
            $byAddress = $devices->filter(fn (Device $device) => $this->sameAddress($device->lan_ip, $ip) || $this->sameAddress($device->ip_address, $ip));
            if ($byAddress->count() === 1) {
                $match = $byAddress->first();
            }
        }
        if (! $match && $ip !== '') {
            $match = $this->deviceForRegister($devices, $ip);
        }
        if (! $match && $ip !== '') {
            $match = $this->deviceForOfficeIp($devices, $ip);
        }
        if (! $match && $name !== '') {
            $match = $devices->first(fn (Device $device) => strcasecmp(trim((string) $device->name), $name) === 0);
        }
        if (! $match && $devices->count() === 1) {
            $match = $devices->first();
        }
        if (! $match && $devices->count() < 2) {
            $registerId = trim((string) config('hikvision.register_id'));
            if ($registerId !== '') {
                $match = $devices->first(fn (Device $device) => strcasecmp((string) $device->register_id, $registerId) === 0);
            }
        }
        if (! $match) {
            $match = $this->deviceForAttendance($devices, $ip, $attendance);
        }
        if ($match && $this->isPrivate($ip) && trim((string) $match->lan_ip) === '') {
            try {
                $match->forceFill(['lan_ip' => $ip])->save();
            } catch (\Throwable) {
            }
        }

        return $match;
    }

    /**
     * Register ids are the office address with the dots removed, so a scan from
     * 192.168.1.156 still finds HIK1921681156 when the row stores the public IP.
     */
    private function deviceForRegister($devices, string $ip): ?Device
    {
        $digits = preg_replace('/\D/', '', $ip) ?? '';
        if (strlen($digits) < 7) {
            return null;
        }

        $matched = $devices->filter(function (Device $device) use ($digits): bool {
            $register = preg_replace('/\D/', '', (string) $device->register_id) ?? '';

            return $register !== '' && $register === $digits;
        });

        return $matched->count() === 1 ? $matched->first() : null;
    }

    private function deviceForOfficeIp($devices, string $ip): ?Device
    {
        $inHost = $this->host((string) config('hikvision.terminals.in.host'));
        $outHost = $this->host((string) config('hikvision.terminals.out.host'));
        if ($inHost !== '' && $outHost !== '' && strcasecmp($inHost, $outHost) === 0) {
            return null;
        }
        if ($inHost !== '' && strcasecmp($inHost, $ip) === 0) {
            $matched = $devices->filter(fn (Device $device): bool => $device->punch_role === Device::ROLE_IN);

            return $matched->count() === 1 ? $matched->first() : null;
        }
        if ($outHost !== '' && strcasecmp($outHost, $ip) === 0) {
            $matched = $devices->filter(fn (Device $device): bool => $device->punch_role === Device::ROLE_OUT);

            return $matched->count() === 1 ? $matched->first() : null;
        }

        return null;
    }

    /**
     * Both doors share one public address. The attendance status says which door it was.
     */
    private function deviceForAttendance($devices, string $ip, string $attendance): ?Device
    {
        $public = $this->host((string) (config('hikvision.public_host') ?: config('hikvision.host')));
        $shared = $ip === '' || ($public !== '' && strcasecmp($public, $ip) === 0);
        if (! $shared) {
            return null;
        }
        $role = match ($attendance) {
            'checkout', 'breakout', 'overtimeout' => Device::ROLE_OUT,
            'checkin', 'breakin', 'overtimein' => Device::ROLE_IN,
            default => null,
        };
        if ($role === null) {
            return null;
        }
        $matched = $devices->filter(fn (Device $device): bool => $device->punch_role === $role);

        return $matched->count() === 1 ? $matched->first() : null;
    }

    private function host(string $host): string
    {
        $host = trim((string) preg_replace('#^https?://#', '', trim($host)));
        $host = explode('/', $host)[0];

        return str_contains($host, ':') ? explode(':', $host)[0] : $host;
    }

    private function isPrivate(string $ip): bool
    {
        return str_starts_with($ip, '10.')
            || str_starts_with($ip, '192.168.')
            || (bool) preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip);
    }

    private function sameAddress(?string $saved, string $reported): bool
    {
        $saved = trim((string) $saved);
        $reported = trim($reported);

        return $saved !== '' && strcasecmp($saved, $reported) === 0;
    }
}
