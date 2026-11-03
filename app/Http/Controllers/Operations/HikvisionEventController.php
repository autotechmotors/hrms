<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Device;
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
