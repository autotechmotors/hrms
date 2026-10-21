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
    public function __construct(private readonly DahuaPush $push) {}

    public function sync(Employee $employee): int
    {
        $devices = $this->devices($employee);
        if ($devices->isEmpty()) {
            $devices = Device::query()->where('company_id', $employee->company_id)->orderBy('id')->get();
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
            try {
                $this->rewrite($device, $employee, $userId, $name);
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
    public function collect(Employee $employee, string $kind, ?string $password = null): array
    {
        $devices = $this->devices($employee);
        if ($devices->isEmpty()) {
            $devices = Device::query()->where('company_id', $employee->company_id)->orderBy('id')->get();
        }
        if ($devices->isEmpty()) {
            return ['devices' => 0, 'message' => 'Add a Dahua device before collecting a credential.'];
        }

        $userId = trim((string) $employee->employee_code);
        $name = trim($employee->full_name);
        if ($userId === '' || $name === '') {
            return ['devices' => 0, 'message' => 'This employee needs a code and a name before the terminal can store them.'];
        }

        $queued = 0;
        foreach ($devices as $device) {
            try {
                $this->push->rememberPerson($device, $userId, $name, $employee->id);
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
                $this->rewrite($device, $employee, $userId, $name);
                if ($kind !== 'password') {
                    $this->push->queueCollect($device, $userId, $kind);
                }
                $queued++;
            } catch (\Throwable) {
                continue;
            }
        }

        $message = match ($kind) {
            'face' => 'Look at '.$devices->first()->name.'. The face is saved here and sent back to the terminal as base64.',
            'finger' => 'Place one finger on '.$devices->first()->name.'. It is saved here and written onto the terminal, the same way a password is stored.',
            'card' => 'Present the card on '.$devices->first()->name.'. It is saved here and written onto the terminal. If this terminal has no card reader, the count stays empty.',
            default => 'Password sent to the terminal.',
        };

        return ['devices' => $queued, 'message' => $message];
    }

    public function keepCollected(Device $device, Employee $employee, array $item): void
    {
        $userId = trim((string) $employee->employee_code);
        $name = trim($employee->full_name);
        if ($userId === '' || $name === '') {
            return;
        }

        $this->rewrite($device, $employee, $userId, $name);
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

        $this->queuePerson($device, $employee, $userId, $name);
        $face = $this->facePayload($employee);
        if ($face) {
            $this->push->queueFace($device, $userId, $face);
        }
        $prints = $this->storedPrints($employee);
        if ($prints !== []) {
            $this->push->queueFingerprints($device, $userId, $prints);
        }
        $cards = $this->storedCards($employee);
        if ($cards !== []) {
            $this->push->queueCards($device, $userId, $cards);
        }
    }

    public function linked(Employee $employee): Collection
    {
        return $this->devices($employee);
    }

    public function drop(Collection $devices, string $userId): void
    {
        $userId = trim($userId);
        if ($userId === '') {
            return;
        }

        foreach ($devices as $device) {
            try {
                $this->push->queueDeleteUser($device, $userId);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    /**
     * @return Collection<int, Device>
     */
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

    private function facePayload(Employee $employee): ?string
    {
        $face = $this->faceFromPhotograph($employee);
        if ($face) {
            EmployeeCredential::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'kind' => 'face', 'slot' => '0'],
                ['content' => $face],
            );

            return $face;
        }

        $stored = $this->faceFromCredential($employee);
        $bytes = is_string($stored) ? base64_decode($stored, true) : false;
        $face = is_string($bytes) && $bytes !== ''
            ? StoredImage::normalize(base64_encode($this->deviceJpeg($bytes)))
            : null;
        if ($face) {
            EmployeeCredential::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'kind' => 'face', 'slot' => '0'],
                ['content' => $face],
            );
        }

        return $face;
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

    private function queuePerson(Device $device, Employee $employee, string $userId, string $name): void
    {
        $pin = $this->storedPin($employee);
        if ($pin !== null) {
            $this->push->queuePassword($device, $userId, $name, $pin);

            return;
        }

        $this->push->queueUpsertPerson($device, $userId, $name);
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

    /**
     * @return list<string>
     */
    private function storedCards(Employee $employee): array
    {
        return EmployeeCredential::query()
            ->where('employee_id', $employee->id)
            ->where('kind', 'card')
            ->orderBy('slot')
            ->pluck('content')
            ->map(function ($content) {
                $card = base64_decode((string) $content, true);

                return is_string($card) ? trim($card) : '';
            })
            ->filter()
            ->values()
            ->all();
    }
}
