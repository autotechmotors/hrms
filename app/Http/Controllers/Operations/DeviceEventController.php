<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Devices\DeviceSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceEventController extends Controller
{
    public function store(Request $request, DeviceSync $sync): JsonResponse
    {
        $token = (string) config('dahua.forward_token');
        $given = (string) $request->header('X-Device-Token', '');
        if ($token === '' || ! hash_equals($token, $given)) {
            abort(401);
        }

        $data = $request->validate([
            'register_id' => ['required', 'string', 'max:100'],
            'seq' => ['required', 'string', 'max:100'],
            'image' => ['nullable', 'string'],
            'event' => ['required', 'array'],
        ]);

        $device = Device::query()->where('register_id', $data['register_id'])->first();
        if (! $device) {
            return response()->json(['message' => 'Device is not on this server.'], 422);
        }

        $result = $sync->receive($device, [
            'RegisterID' => $data['register_id'],
            'NotifyInfo' => ['Events' => [$data['event']]],
        ], $data['seq'], $data['image'] ?? null);

        return response()->json($result);
    }
}
