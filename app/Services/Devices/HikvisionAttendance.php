<?php

namespace App\Services\Devices;

use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

class HikvisionAttendance
{
    public function __construct(
        private readonly HikvisionEvents $events,
        private readonly DeviceSync $sync,
        private readonly PunchForward $forward,
        private readonly DevicePeople $people,
    ) {}

    /**
     * @return array{stored: int, held: int, added: int, paused: bool}
     */
    public function pull(bool $full = true): array
    {
        $password = $this->secret();
        if ($password === '') {
            throw new RuntimeException('Set HIKVISION_PASSWORD to the face terminal login, then run hikvision:pull again.');
        }
        if (! Cache::add('hikvision.pulling', 1, now()->addSeconds(20))) {
            return ['stored' => 0, 'held' => 0, 'added' => 0, 'paused' => false];
        }

        try {
            return $this->pullUnlocked($password, $full);
        } finally {
            Cache::forget('hikvision.pulling');
        }
    }

    /**
     * @return array{stored: int, held: int, added: int, paused: bool}
     */
    private function pullUnlocked(string $password, bool $full): array
    {
        $imported = $full ? $this->importHeld() : ['stored' => 0, 'added' => 0];
        $stored = $imported['stored'];
        $held = 0;
        $added = $imported['added'];
        $paused = false;
        foreach ($this->targets() as $registerId => $host) {
            $device = Device::query()->get()->first(fn (Device $row) => strcasecmp((string) $row->register_id, (string) $registerId) === 0);
            $username = $device && trim((string) $device->username) !== '' ? $device->terminalUsername() : (string) config('hikvision.username');
            $secret = $device && trim((string) $device->password) !== '' ? $device->terminalPassword() : $password;
            $result = $this->pullTarget((string) $registerId, $host, $username, $secret, $full);
            $stored += $result['stored'];
            $held += $result['held'];
            $added += $result['added'];
            $paused = $paused || $result['paused'];
        }

        return ['stored' => $stored, 'held' => $held, 'added' => $added, 'paused' => $paused];
    }

    /**
     * @return array{stored: int, added: int}
     */
    private function importHeld(): array
    {
        $dir = (string) config('dahua.forward_dir');
        if (! is_dir($dir)) {
            return ['stored' => 0, 'added' => 0];
        }

        $indexPath = $dir.DIRECTORY_SEPARATOR.'.local-index';
        $done = [];
        if (is_file($indexPath)) {
            $decoded = json_decode((string) file_get_contents($indexPath), true);
            $done = is_array($decoded) ? $decoded : [];
        } else {
            foreach (File::files($dir) as $file) {
                if (str_ends_with(strtolower($file->getFilename()), '.json')) {
                    $done[$file->getFilename()] = 1;
                }
            }
            if ($done !== []) {
                File::put($indexPath, json_encode($done));
            }

            return ['stored' => 0, 'added' => 0];
        }

        $stored = 0;
        $added = 0;
        $changed = false;
        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if (! str_ends_with(strtolower($name), '.json') || isset($done[$name])) {
                continue;
            }
            $done[$name] = 1;
            $changed = true;
            $payload = json_decode(File::get($file->getPathname()), true);
            if (! is_array($payload)) {
                continue;
            }
            $registerId = (string) ($payload['register_id'] ?? '');
            $device = Device::query()->get()->first(fn (Device $row) => strcasecmp((string) $row->register_id, $registerId) === 0);
            $event = $payload['event'] ?? null;
            $data = is_array($event) ? ($event['Data'] ?? null) : null;
            if (! $device || ! is_array($event) || ! is_array($data)) {
                continue;
            }
            $userId = trim((string) ($data['UserID'] ?? ''));
            if ($userId === '' || ! $device->isHikvision()) {
                continue;
            }
            $added += $this->preparePerson($device, $userId, (string) ($data['UserName'] ?? $userId), $payload['image'] ?? null);
            $result = $this->sync->receive($device, [
                'RegisterID' => $registerId,
                'NotifyInfo' => ['Events' => [$event]],
            ], (string) ($payload['seq'] ?? ''), isset($payload['image']) ? (string) $payload['image'] : null);
            $stored += (int) ($result['saved'] ?? 0);
        }
        if ($changed) {
            File::put($indexPath, json_encode($done));
        }

        return ['stored' => $stored, 'added' => $added];
    }

    /**
     * @return array{stored: int, held: int, added: int, paused: bool}
     */
    private function pullTarget(string $registerId, string $host, string $username, string $password, bool $full): array
    {
        $block = 'hikvision.auth.'.hash('sha256', $host.'|'.$password);
        if (Cache::get($block)) {
            return ['stored' => 0, 'held' => 0, 'added' => 0, 'paused' => true];
        }

        $this->events->alignClock($host, $username, $password);
        $from = $this->cursor($registerId);
        $to = now();
        try {
            $scans = $this->events->faceScans($host, $username, $password, $from, $to);
        } catch (HikvisionAuthException $exception) {
            Cache::put($block, 1, now()->addMinutes(20));
            $this->mark($registerId, 'offline');
            throw $exception;
        } catch (RuntimeException $exception) {
            $this->mark($registerId, 'offline');
            throw $exception;
        }

        $device = $this->deviceFor($registerId, $host);
        $stored = 0;
        $held = 0;
        $added = 0;
        $finished = true;
        foreach ($scans as $scan) {
            $result = $this->publish($device, $scan);
            if ($result['added']) {
                $added++;
            }
            if ($result['held']) {
                $held++;
            }
            if (! $result['done']) {
                $finished = false;
                Cache::put($this->cursorKey($registerId), Carbon::createFromTimestampUTC($scan['utc'])->toIso8601String(), now()->addDays(40));
                break;
            }
            if ($result['stored']) {
                $stored++;
            }
            Cache::put($this->cursorKey($registerId), Carbon::createFromTimestampUTC($scan['utc'])->addSecond()->toIso8601String(), now()->addDays(40));
        }
        if ($finished) {
            $next = $to->copy()->subSeconds(20);
            $current = Cache::get($this->cursorKey($registerId));
            if (is_string($current) && $current !== '' && Carbon::parse($current)->greaterThan($next)) {
                $next = Carbon::parse($current);
            }
            Cache::put($this->cursorKey($registerId), $next->toIso8601String(), now()->addDays(40));
        }
        if ($full) {
            $added += $this->importEnrolled($device, $host, $username, $password);
        }

        return ['stored' => $stored, 'held' => $held, 'added' => $added, 'paused' => false];
    }

    /**
     * @param  array{serial: string, user_id: string, name: string, utc: int, type: string, image: ?string}  $scan
     * @return array{done: bool, stored: bool, held: bool, added: bool}
     */
    private function publish(Device $device, array $scan): array
    {
        $event = [
            'Code' => 'AccessControl',
            'Data' => [
                'ErrorCode' => 0,
                'Type' => $scan['type'],
                'RealUTC' => $scan['utc'],
                'UserID' => $scan['user_id'],
                'UserName' => $scan['name'],
                'SN' => (string) $device->register_id,
            ],
        ];
        $local = null;
        $added = false;
        if ($device->exists) {
            $added = $this->preparePerson($device, $scan['user_id'], $scan['name'], $scan['image']) === 1;
            $local = $this->sync->receive($device, [
                'RegisterID' => (string) $device->register_id,
                'NotifyInfo' => ['Events' => [$event]],
            ], $scan['serial'], $scan['image']);
        }
        $forward = $this->forward->outcome($device, $event, $scan['serial'], $scan['image']);
        $localSaved = (int) ($local['saved'] ?? 0);
        $stored = $localSaved > 0 || ($forward === 'sent' && ($local === null || ($local['pending'] ?? false)));
        $done = in_array($forward, ['sent', 'queued'], true)
            || ($forward === 'skipped' && $local !== null && ! ($local['pending'] ?? true));

        return [
            'done' => $done,
            'stored' => $done && $stored,
            'held' => $forward === 'queued' || ($forward === 'skipped' && $local !== null && ($local['pending'] ?? false)),
            'added' => $added,
        ];
    }

    private function importEnrolled(Device $device, string $host, string $username, string $password): int
    {
        if (! $device->exists) {
            return 0;
        }

        $countKey = 'hikvision.users.'.$device->register_id;
        $attemptKey = 'hikvision.enroll.attempt.'.$device->register_id;
        $count = $this->events->userCount($host, $username, $password);
        $known = Cache::get($countKey);
        if ($count !== null && $known !== null && (int) $known === $count) {
            return 0;
        }
        if ($count === null && Cache::has($attemptKey)) {
            return 0;
        }
        Cache::put($attemptKey, 1, now()->addMinutes(10));

        try {
            $people = $this->events->enrolledOn($host, $username, $password, now());
        } catch (RuntimeException $exception) {
            DahuaLog::debug('hikvision enrolled people', ['error' => $exception->getMessage()]);

            return 0;
        }
        if ($count !== null) {
            Cache::put($countKey, $count, now()->addDays(40));
        }

        $added = 0;
        foreach ($people as $person) {
            $employeeId = Employee::query()
                ->where('company_id', $device->company_id)
                ->where('employee_code', $person['user_id'])
                ->value('id');
            $hasFace = $employeeId && EmployeeCredential::query()
                ->where('employee_id', $employeeId)
                ->where('kind', 'face')
                ->exists();
            $image = $hasFace ? null : $this->events->facePhoto($host, $username, $password, $person['user_id'], $person['face_url']);
            $added += $this->preparePerson($device, $person['user_id'], $person['name'], $image);
            if (! $employeeId) {
                $employeeId = Employee::query()
                    ->where('company_id', $device->company_id)
                    ->where('employee_code', $person['user_id'])
                    ->value('id');
            }
            if (! $employeeId) {
                continue;
            }
            $dismissed = DeviceTerminalUser::query()
                ->where('device_id', $device->id)
                ->where('user_id', $person['user_id'])
                ->whereNotNull('dismissed_at')
                ->exists();
            if ($dismissed) {
                continue;
            }
            DeviceTerminalUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'user_id' => $person['user_id']],
                ['name' => $person['name'], 'employee_id' => $employeeId],
            );
        }

        return $added;
    }

    private function preparePerson(Device $device, string $userId, string $name, ?string $image): int
    {
        $exists = Employee::query()
            ->where('company_id', $device->company_id)
            ->where('employee_code', $userId)
            ->exists();
        $employee = $this->people->ensure($device, $userId, $name);
        if (! $employee) {
            return 0;
        }
        if (is_string($image) && $image !== '' && ! EmployeeCredential::query()->where('employee_id', $employee->id)->where('kind', 'face')->exists()) {
            $this->people->storeItem($employee, ['PhotoData' => [$image]]);
        }

        return $exists ? 0 : 1;
    }

    private function secret(): string
    {
        foreach (Device::query()->orderBy('id')->get() as $device) {
            if ($device->isHikvision() && trim((string) $device->password) !== '') {
                return $device->terminalPassword();
            }
        }

        return trim((string) config('hikvision.password'));
    }

    /**
     * @return array<string, string>
     */
    private function targets(): array
    {
        $rows = [];
        Device::query()->orderBy('id')->get()
            ->filter(fn (Device $device) => $device->isHikvision())
            ->each(function (Device $device) use (&$rows): void {
                $host = $device->terminalHost();
                if ($host !== '') {
                    $rows[(string) $device->register_id] = $host;
                }
            });
        $host = trim((string) config('hikvision.host'));
        $registerId = trim((string) config('hikvision.register_id'));
        if ($host !== '' && $registerId !== '' && ! array_key_exists($registerId, $rows)) {
            $port = is_numeric(config('hikvision.port')) ? (int) config('hikvision.port') : 0;
            $rows[$registerId] = $port > 80 ? $host.':'.$port : $host;
        }

        return $rows;
    }

    private function deviceFor(string $registerId, string $host): Device
    {
        $device = Device::query()->get()->first(fn (Device $row) => strcasecmp((string) $row->register_id, $registerId) === 0);
        if ($device) {
            $device->update(array_filter([
                'ip_address' => str_contains($host, ':') ? null : $host,
                'status' => 'online',
                'last_checked_at' => now(),
            ], fn ($value) => $value !== null));

            return $device->fresh() ?? $device;
        }

        $companyId = Device::query()->value('company_id') ?: Company::query()->value('id');
        if (! $companyId) {
            return new Device([
                'name' => 'Hikvision',
                'register_id' => $registerId,
                'ip_address' => $host,
            ]);
        }

        return Device::query()->create([
            'company_id' => $companyId,
            'name' => 'Hikvision',
            'model' => 'Hikvision',
            'register_id' => $registerId,
            'ip_address' => $host,
            'status' => 'online',
            'last_checked_at' => now(),
        ]);
    }

    private function mark(string $registerId, string $status): void
    {
        Device::query()->get()
            ->first(fn (Device $row) => strcasecmp((string) $row->register_id, $registerId) === 0)
            ?->update(['status' => $status, 'last_checked_at' => now()]);
    }

    private function cursor(string $registerId): Carbon
    {
        $stored = Cache::get($this->cursorKey($registerId));
        if (is_string($stored) && $stored !== '') {
            return Carbon::parse($stored);
        }

        return now()->subDays(7)->startOfDay();
    }

    private function cursorKey(string $registerId): string
    {
        return 'hikvision.cursor.'.$registerId;
    }
}
