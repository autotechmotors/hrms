<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Services\Devices\DevicePeople;
use App\Services\Devices\DeviceSync;
use App\Services\Devices\HikvisionEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HikvisionEventController extends Controller
{
    public function __invoke(Request $request, string $token, HikvisionEvents $events, DeviceSync $sync): JsonResponse|Response
    {
        $expected = (string) config('dahua.forward_token');
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

        $device = $this->device($scan['ip']);
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
        $expected = (string) config('dahua.forward_token');
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

    private function device(string $ip): ?Device
    {
        $devices = Device::query()->get()->filter(fn (Device $device) => $device->isHikvision())->values();
        if ($ip !== '') {
            $match = $devices->first(fn (Device $device) => trim((string) $device->ip_address) === $ip);
            if ($match) {
                return $match;
            }
        }

        $registerId = trim((string) config('hikvision.register_id'));
        if ($registerId !== '') {
            $match = $devices->first(fn (Device $device) => strcasecmp((string) $device->register_id, $registerId) === 0);
            if ($match) {
                return $match;
            }
        }

        return $devices->count() === 1 ? $devices->first() : null;
    }
}
