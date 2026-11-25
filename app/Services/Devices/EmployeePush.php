<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Support\StoredImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class EmployeePush
{
    public function __construct(
        private readonly HikvisionEnroll $hikvision,
        private readonly HikvisionJobs $jobs,
    ) {}

    public function sync(Employee $employee): int
    {
        return $this->send($employee, false);
    }

    public function saveToTerminals(Employee $employee): int
    {
        return $this->send($employee, true);
    }

    private function send(Employee $employee, bool $includeFaceTerminal): int
    {
        $devices = $this->devices($employee);
        if ($devices->isEmpty()) {
            $devices = Device::query()
                ->where('company_id', $employee->company_id)
                ->where('gate_kind', '!=', Device::KIND_GATE)
                ->orderBy('id')
                ->get();
        }
        if ($devices->isEmpty()) {
            return 0;
        }

        $userId = trim((string) $employee->employee_code);
        $name = trim($employee->full_name);
        if ($userId === '' || $name === '') {
            return 0;
        }

        $queued = 0;
        foreach ($devices as $device) {
            if (! $device->isHikvision()) {
                continue;
            }
            if (! $includeFaceTerminal) {
                continue;
            }
            try {
                $this->placeOnTerminal($device, $employee, $userId, $name);
                DeviceUser::query()->updateOrCreate(
                    ['device_id' => $device->id, 'employee_id' => $employee->id],
                    ['is_blocked' => false],
                );
                DeviceTerminalUser::query()->updateOrCreate(
                    ['device_id' => $device->id, 'user_id' => $userId],
                    ['name' => $name, 'employee_id' => $employee->id, 'dismissed_at' => null],
                );
                $queued++;
            } catch (\Throwable) {
                continue;
            }
        }

        return $queued;
    }

    /**
     * @return array{devices: int, message: string}
     */
    public function collect(Employee $employee, string $kind, ?string $password = null, ?Device $only = null): array
    {
        if ($only) {
            $devices = collect([$only]);
        } else {
            $devices = $this->devices($employee);
            if ($devices->isEmpty()) {
                $devices = Device::query()
                    ->where('company_id', $employee->company_id)
                    ->where('gate_kind', '!=', Device::KIND_GATE)
                    ->orderBy('id')
                    ->get();
            }
        }
        if ($devices->isEmpty()) {
            return ['devices' => 0, 'message' => 'Add a device before collecting a credential.'];
        }

        $userId = trim((string) $employee->employee_code);
        $name = trim($employee->full_name);
        if ($userId === '' || $name === '') {
            return ['devices' => 0, 'message' => 'This employee needs a code and a name before the terminal can store them.'];
        }

        if (! $only) {
            $devices = Device::query()
                ->where('company_id', $employee->company_id)
                ->orderBy('id')
                ->get()
                ->filter(fn (Device $device) => $device->isHikvision())
                ->merge($devices)
                ->unique('id')
                ->filter(fn (Device $device) => $device->isHikvision())
                ->values();
        }
        $queued = 0;
        $hikvision = null;
        $hikvisionError = null;
        foreach ($devices as $device) {
            try {
                DeviceUser::query()->updateOrCreate(
                    ['device_id' => $device->id, 'employee_id' => $employee->id],
                    ['is_blocked' => false],
                );
                if ($kind === 'password') {
                    EmployeeCredential::query()->updateOrCreate(
                        ['employee_id' => $employee->id, 'kind' => 'password', 'slot' => '0'],
                        ['content' => base64_encode((string) $password)],
                    );
                }
                $this->collectOnTerminal($device, $employee, $userId, $name, $kind, $password);
                DeviceTerminalUser::query()->updateOrCreate(
                    ['device_id' => $device->id, 'user_id' => $userId],
                    ['name' => $name, 'employee_id' => $employee->id, 'dismissed_at' => null, 'synced_at' => now()],
                );
                $hikvision = $device;
                $queued++;
            } catch (\Throwable $exception) {
                $hikvisionError = $exception->getMessage();

                continue;
            }
        }

        if ($queued === 0) {
            return ['devices' => 0, 'message' => $hikvisionError ?: 'Add a Hikvision device before collecting a credential.'];
        }

        $copied = [];
        $missed = [];
        if (in_array($kind, ['face', 'finger'], true) && $hikvision instanceof Device) {
            foreach ($this->otherTerminals($employee, $hikvision) as $other) {
                try {
                    $this->placeOnTerminal($other, $employee, $userId, $name, false);
                    DeviceTerminalUser::query()->updateOrCreate(
                        ['device_id' => $other->id, 'user_id' => $userId],
                        ['name' => $name, 'employee_id' => $employee->id, 'dismissed_at' => null, 'synced_at' => now()],
                    );
                    $copied[] = $other->name;
                } catch (\Throwable) {
                    $missed[] = $other->name;
                }
            }
        }

        $target = $hikvision->name;
        $message = match ($kind) {
            'face' => 'Face saved on '.$target.'.',
            'finger' => 'Finger saved on '.$target.'.',
            'card' => $target.' adds a face or a finger. Stand at that terminal and use those buttons.',
            default => 'Password sent to '.$target.'.',
        };
        if ($copied !== []) {
            $message .= ' Copied to '.implode(', ', $copied).'.';
        }
        if ($missed !== []) {
            $message .= ' Not copied to '.implode(', ', $missed).'.';
        }

        return ['devices' => $queued, 'message' => $message];
    }

    /**
     * A person update on this terminal replaces the user and drops face, finger, and card.
     * The stored credentials are always written again immediately after the person.
     */
    public function rewrite(Device $device, Employee $employee, ?string $userId = null, ?string $name = null): void
    {
        $userId = trim($userId ?? (string) $employee->employee_code);
        $name = trim($name ?? (string) $employee->full_name);
        if ($userId === '' || $name === '') {
            return;
        }

        if (! $device->isHikvision()) {
            return;
        }

        $this->placeOnTerminal($device, $employee, $userId, $name);
    }

    public function faceAccess(Employee $employee, Device $device, bool $enabled): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $userId = trim((string) $employee->employee_code);
        $name = trim($employee->full_name);
        if ($userId === '' || $name === '' || ! $device->isHikvision()) {
            return;
        }

        $jpeg = $enabled ? $this->faceJpeg($employee) : null;
        if ($this->hikvision->reachable($device)) {
            $this->hikvision->setUserType($device, $userId, $name, $enabled ? 'normal' : 'blackList');
            if ($enabled && $jpeg !== null) {
                $this->hikvision->replaceFace($device, $userId, $jpeg);
            }

            return;
        }

        $result = $this->jobs->dispatchAndWait([
            'kind' => 'face-access',
            'employee_id' => $employee->id,
            'register_id' => $device->register_id,
            'code' => $userId,
            'name' => $name,
            'enabled' => $enabled,
            'face' => $jpeg !== null ? base64_encode($jpeg) : null,
        ], 25);
        if (($result['status'] ?? '') !== 'done') {
            throw new \RuntimeException($result['message'] ?: 'The office link to the face terminal is not running.');
        }
    }

    public function linked(Employee $employee): Collection
    {
        return $this->devices($employee);
    }

    /**
     * @return array{devices: int, message: string}
     */
    public function removeCredential(Employee $employee, EmployeeCredential $credential): array
    {
        if ((int) $credential->employee_id !== (int) $employee->id) {
            throw new \RuntimeException('That credential belongs to another employee.');
        }

        $userId = trim((string) $employee->employee_code);
        $devices = Device::query()
            ->where('company_id', $employee->company_id)
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision());
        $reached = 0;
        $failure = null;
        foreach ($devices as $device) {
            try {
                $this->removeFromDevice($device, $employee, $credential, $userId);
                $reached++;
            } catch (\Throwable $exception) {
                $failure = $exception->getMessage();
            }
        }
        if ($reached === 0 && $failure !== null) {
            throw new \RuntimeException($failure);
        }

        $credential->delete();
        $label = $credential->kind === 'finger' ? 'Finger' : 'Face';

        return ['devices' => $reached, 'message' => $label.' removed from HRMS and the face terminal.'];
    }

    private function removeFromDevice(Device $device, Employee $employee, EmployeeCredential $credential, string $userId): void
    {
        if ($credential->kind === 'face') {
            $remaining = EmployeeCredential::query()
                ->where('employee_id', $employee->id)
                ->where('kind', 'face')
                ->whereKeyNot($credential->id)
                ->orderByDesc('id')
                ->get();
            if ($remaining->isEmpty()) {
                $this->hikvision->deleteFace($device, $userId);
            } else {
                $bytes = StoredImage::bytes((string) $remaining->first()->content);
                if (! is_string($bytes) || ! str_starts_with($bytes, "\xFF\xD8\xFF")) {
                    throw new \RuntimeException('The remaining face could not be sent back to '.$device->name.'.');
                }
                $this->hikvision->replaceFace($device, $userId, $bytes);
            }
        }
        if ($credential->kind === 'finger') {
            $this->hikvision->deleteFinger($device, $userId, (int) $credential->slot);
        }
    }

    public function drop(Collection $devices, string $userId): void
    {
        $userId = trim($userId);
        if ($userId === '') {
            return;
        }

        foreach ($devices as $device) {
            if (! $device->isHikvision()) {
                continue;
            }
            try {
                if ($this->hikvision->reachable($device)) {
                    $this->hikvision->remove($device, $userId);
                } else {
                    $this->jobs->push(['kind' => 'remove', 'code' => $userId, 'name' => $userId]);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    /**
     * @return Collection<int, Device>
     */
    private function placeOnTerminal(Device $device, Employee $employee, string $userId, string $name, bool $wait = true): void
    {
        $pin = $this->storedPin($employee);
        $face = $this->facePayload($employee);
        $prints = $this->storedPrints($employee);
        if ($this->hikvision->reachable($device)) {
            $this->hikvision->storeProfile($device, $userId, $name, $pin, $face, $prints, true);

            return;
        }
        if (! $wait) {
            throw new \RuntimeException($device->name.' is offline ('.$device->terminalHost().').');
        }

        $result = $this->jobs->dispatchAndWait([
            'kind' => 'profile',
            'employee_id' => $employee->id,
            'code' => $userId,
            'name' => $name,
            'password' => $pin,
            'face' => $face,
            'prints' => $prints,
        ], 20);
        if (($result['status'] ?? '') !== 'done') {
            throw new \RuntimeException($result['message'] ?: 'The office link to the face terminal is not running.');
        }
    }

    private function collectOnTerminal(Device $device, Employee $employee, string $userId, string $name, string $kind, ?string $password): void
    {
        try {
            if ($this->hikvision->reachable($device)) {
                $this->hikvision->capture($device, $employee, $kind, $password);
                if ($device->status !== 'online') {
                    $device->update(['status' => 'online']);
                }

                return;
            }
            if (trim((string) $device->ip_address) !== '') {
                if ($device->status !== 'offline') {
                    $device->update(['status' => 'offline']);
                }
                throw new \RuntimeException($device->name.' is offline ('.$device->terminalHost().'). Check power and the LAN cable.');
            }
            if ($kind === 'card') {
                throw new \RuntimeException($device->name.' adds a face or a finger. Stand at that terminal and use those buttons.');
            }

            $job = [
                'kind' => $kind,
                'employee_id' => $employee->id,
                'code' => $userId,
                'name' => $name,
                'password' => $password,
                'register_id' => $device->register_id,
            ];
            if ($kind === 'finger') {
                $job['finger_no'] = EmployeeCredential::query()
                    ->where('employee_id', $employee->id)
                    ->where('kind', 'finger')
                    ->count() + 1;
            }
            $result = $this->jobs->dispatchAndWait($job, in_array($kind, ['face', 'finger'], true) ? 70 : 15);
            if (($result['status'] ?? '') !== 'done') {
                throw new \RuntimeException($result['message'] ?: 'The office link to the face terminal is not running.');
            }
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains(strtolower($message), 'connection failed') || str_contains(strtolower($message), 'timed out')) {
                if ($device->status !== 'offline') {
                    $device->update(['status' => 'offline']);
                }
                throw new \RuntimeException($device->name.' is offline ('.$device->terminalHost().'). Check power and the LAN cable.');
            }

            throw $exception;
        }
    }

    private function devices(Employee $employee): Collection
    {
        $ids = DeviceUser::query()->where('employee_id', $employee->id)->pluck('device_id')
            ->merge(DeviceTerminalUser::query()->where('employee_id', $employee->id)->pluck('device_id'))
            ->unique()
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Device::query()->where('company_id', $employee->company_id)->whereIn('id', $ids)->get();
    }

    private function keepTerminalFace(Device $device, Employee $employee, string $userId): void
    {
        $saved = app(HikvisionEvents::class)->facePhoto(
            $device->terminalHost(),
            $device->terminalUsername(),
            $device->terminalPassword(),
            $userId,
        );
        if (! is_string($saved) || $saved === '') {
            return;
        }
        $bytes = base64_decode($saved, true);
        if (! is_string($bytes) || ! str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return;
        }

        EmployeeCredential::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'kind' => 'face', 'slot' => '0'],
            ['content' => $saved],
        );
    }

    private function faceJpeg(Employee $employee): ?string
    {
        $stored = EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->where('kind', 'face')
            ->orderBy('slot')
            ->value('content');
        if (! is_string($stored) || $stored === '') {
            return null;
        }
        $bytes = base64_decode($stored, true);

        return is_string($bytes) && str_starts_with($bytes, "\xFF\xD8\xFF") ? $bytes : null;
    }

    /**
     * @return Collection<int, Device>
     */
    private function otherTerminals(Employee $employee, Device $source): Collection
    {
        return Device::query()
            ->where('company_id', $employee->company_id)
            ->where('id', '!=', $source->id)
            ->orderBy('id')
            ->get()
            ->filter(fn (Device $device) => $device->isHikvision())
            ->values();
    }

    private function facePayload(Employee $employee): ?string
    {
        $stored = $this->faceFromCredential($employee);
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return $this->faceFromPhotograph($employee);
    }

    private function faceFromPhotograph(Employee $employee): ?string
    {
        $path = (string) $employee->photograph_path;
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($path);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $bytes = $this->deviceJpeg($bytes);

        return StoredImage::normalize(base64_encode($bytes));
    }

    private function faceFromCredential(Employee $employee): ?string
    {
        $stored = EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->where('kind', 'face')
            ->orderBy('slot')
            ->value('content');

        return is_string($stored) ? StoredImage::normalize($stored) : null;
    }

    private function deviceJpeg(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return $bytes;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 480;
        if ($width > $max || $height > $max) {
            $scale = $max / max($width, $height);
            $scaled = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagecopyresampled($scaled, $image, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), $width, $height);
            imagedestroy($image);
            $image = $scaled;
        }

        $jpeg = '';
        foreach ([80, 65, 50, 40] as $quality) {
            ob_start();
            imagejpeg($image, null, $quality);
            $jpeg = (string) ob_get_clean();
            if ($jpeg !== '' && strlen($jpeg) <= 80 * 1024) {
                break;
            }
        }
        imagedestroy($image);

        return $jpeg !== '' ? $jpeg : $bytes;
    }

    private function storedPin(Employee $employee): ?string
    {
        $stored = EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->where('kind', 'password')
            ->orderBy('slot')
            ->value('content');
        $pin = is_string($stored) ? base64_decode($stored, true) : false;

        return is_string($pin) && preg_match('/^\d{4,8}$/', $pin) === 1 ? $pin : null;
    }

    /**
     * @return list<string>
     */
    private function storedPrints(Employee $employee): array
    {
        return EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->where('kind', 'finger')
            ->orderBy('slot')
            ->pluck('content')
            ->map(fn ($content) => StoredImage::normalize((string) $content))
            ->filter()
            ->values()
            ->all();
    }
}
