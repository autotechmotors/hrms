<?php

namespace App\Services\Devices;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Throwable;

class HikvisionRelay
{
    public function __construct(private readonly HikvisionEnroll $enroll) {}

    public function drain(): ?string
    {
        if (! config('hikvision.pull') || (string) config('hikvision.password') === '') {
            return null;
        }
        $origin = $this->origin();
        $token = (string) config('devices.forward_token');
        if ($origin === '' || $token === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)->acceptJson()->get($origin.'/hikvision-events/'.$token.'/jobs/next');
        } catch (Throwable) {
            return null;
        }
        if (! $response->ok()) {
            return null;
        }
        $job = $response->json('job');
        if (! is_array($job) || ! isset($job['id'])) {
            return null;
        }

        try {
            $result = $this->perform($job);
            $result['ok'] = true;
        } catch (Throwable $exception) {
            $result = ['ok' => false, 'message' => $exception->getMessage()];
        }

        try {
            Http::timeout(20)->acceptJson()->post($origin.'/hikvision-events/'.$token.'/jobs/'.$job['id'], $result);
        } catch (Throwable) {
            return null;
        }

        return (string) ($job['code'] ?? '').' '.$job['kind'];
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    private function perform(array $job): array
    {
        $device = $this->device($job);
        $code = trim((string) ($job['code'] ?? ''));
        $name = trim((string) ($job['name'] ?? $code));
        $kind = (string) ($job['kind'] ?? '');
        if ($code === '' || $name === '') {
            throw new \RuntimeException('The terminal job has no employee code.');
        }

        if ($kind === 'remove') {
            $this->enroll->remove($device, $code);

            return [];
        }
        if ($kind === 'face-access') {
            $enabled = (bool) ($job['enabled'] ?? true);
            $this->enroll->setUserType($device, $code, $name, $enabled ? 'normal' : 'blackList');
            if ($enabled && is_string($job['face'] ?? null) && $job['face'] !== '') {
                $jpeg = base64_decode($job['face'], true);
                if (is_string($jpeg) && str_starts_with($jpeg, "\xFF\xD8\xFF")) {
                    $this->enroll->replaceFace($device, $code, $jpeg);
                }
            }

            return [];
        }
        if ($kind === 'face') {
            return ['face' => base64_encode($this->enroll->takenFace($device, $code, $name))];
        }
        if ($kind === 'finger') {
            $slot = (int) ($job['finger_no'] ?? 1);

            return ['finger' => $this->enroll->takenFinger($device, $code, $name, $slot), 'slot' => $slot];
        }

        $this->enroll->storeProfile(
            $device,
            $code,
            $name,
            is_string($job['password'] ?? null) ? $job['password'] : null,
            is_string($job['face'] ?? null) ? $job['face'] : null,
            is_array($job['prints'] ?? null) ? $job['prints'] : [],
        );

        return [];
    }

    private function device(array $job = []): Device
    {
        $registerId = trim((string) ($job['register_id'] ?? ''));
        $found = Device::query()->get()->first(function (Device $device) use ($registerId): bool {
            if (! $device->isHikvision()) {
                return false;
            }

            return $registerId !== '' && strcasecmp((string) $device->register_id, $registerId) === 0;
        }) ?: Device::query()->get()->first(fn (Device $device) => $device->isHikvision());
        if ($found) {
            if (trim((string) $found->ip_address) === '') {
                $found->ip_address = (string) config('hikvision.host');
            }

            return $found;
        }

        $device = new Device([
            'name' => 'Hikvision',
            'register_id' => (string) config('hikvision.register_id'),
            'ip_address' => (string) config('hikvision.host'),
        ]);

        return $device;
    }

    private function origin(): string
    {
        $url = (string) config('devices.forward_url');
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
