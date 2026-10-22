<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\DeviceUser;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DahuaPush
{
    private int $queueSequence = 0;

    public function registerId(Device $device): string
    {
        $id = trim((string) ($device->register_id ?: config('dahua.register_id')));

        if ($id === '') {
            throw new RuntimeException('Set the device register ID before sending a command.');
        }

        return $id;
    }

    public function listenerOpen(): bool
    {
        $socket = @fsockopen('127.0.0.1', (int) config('dahua.port'), $errno, $errstr, 0.4);
        if ($socket !== false) {
            fclose($socket);

            return true;
        }

        return $this->webGateway();
    }

    public function webGateway(): bool
    {
        $configured = rtrim((string) config('dahua.push_dir'), '\\/');

        return $configured === '' || $this->foreignPath($configured);
    }

    public function connection(): ?array
    {
        $path = $this->pushDir().DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'latest.json';
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return null;
        }

        $seen = isset($data['last_seen']) ? Carbon::parse($data['last_seen']) : null;
        $data['online'] = $seen !== null && $seen->greaterThan(now()->subSeconds(45));
        $data['last_seen_at'] = $seen;

        return $data;
    }

    public function reachable(Device $device): bool
    {
        $connection = $this->connection();
        if ($connection === null || empty($connection['online'])) {
            return false;
        }

        $seen = $this->safe((string) ($connection['register_id'] ?? ''));

        return $seen === '' || $seen === $this->safe($this->registerId($device));
    }

    public function collectPhase(Device $device): string
    {
        if (! $this->reachable($device)) {
            return 'offline';
        }
        if ($this->collectCommandWaiting($device)) {
            return 'waiting';
        }
        if ($this->activeCollect($device) || $this->sentCollectRecently($device)) {
            return 'detected';
        }

        return 'idle';
    }

    public function lastResult(): ?array
    {
        $path = $this->pushDir().DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'last_result.json';
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    public function queueAddPerson(Device $device, Employee $employee, ?string $expiresOn): string
    {
        $userId = trim((string) $employee->employee_code);
        if ($userId === '') {
            throw new RuntimeException('This employee has no employee code, so the terminal cannot store them.');
        }

        return $this->queueUpsertPerson($device, $userId, $employee->full_name);
    }

    public function queueUpsertPerson(Device $device, string $userId, string $name): string
    {
        $userId = trim($userId);
        $name = trim($name);
        if ($userId === '') {
            throw new RuntimeException('This person has no user id, so the terminal cannot store them.');
        }
        if ($name === '') {
            throw new RuntimeException('This person has no name.');
        }

        return $this->queue($device, 'add-'.$userId, $this->command('POST', '/ezapi/ac/persons', $this->personBody($userId, $name)));
    }

    public function queuePassword(Device $device, string $userId, string $name, string $password): string
    {
        return $this->queue($device, 'pass-'.$userId, $this->command('POST', '/ezapi/ac/persons', $this->personBody($userId, $name, $password)));
    }

    public function queueCollect(Device $device, string $userId, string $kind): string
    {
        $types = ['face' => 0, 'finger' => 2, 'card' => 3];
        if (! isset($types[$kind])) {
            throw new RuntimeException('Choose a face, finger, or card to collect.');
        }

        $this->releaseCollect($device);
        $this->rememberCollect($device, $userId, $kind);

        return $this->queue($device, 'collect-'.$kind.'-'.$userId, $this->command('POST', '/ezapi/ac/personCollect', [
            'Type' => $types[$kind],
            'UserID' => $userId,
        ]));
    }

    public function queueFace(Device $device, string $userId, string $photo): string
    {
        $userId = trim($userId);
        $photo = trim($photo);
        if ($userId === '' || $photo === '') {
            throw new RuntimeException('A face update needs a user id and a photo.');
        }

        return $this->queue($device, 'face-'.$userId, $this->command('POST', '/ezapi/ac/faces', [
            'FaceList' => [[
                'UserID' => $userId,
                'PhotoData' => [$photo],
                'FeatureData' => [],
            ]],
        ]));
    }

    /**
     * @param  list<string>  $prints
     */
    public function queueFingerprints(Device $device, string $userId, array $prints): string
    {
        $userId = trim($userId);
        $rows = [];
        foreach (array_values($prints) as $index => $print) {
            $print = trim((string) $print);
            if ($print === '') {
                continue;
            }
            $rows[] = [
                'FingerprintName' => 'Finger '.($index + 1),
                'Type' => 0,
                'FingerprintData' => $print,
            ];
        }
        if ($userId === '' || $rows === []) {
            throw new RuntimeException('A fingerprint update needs a user id and print data.');
        }

        return $this->queue($device, 'finger-'.$userId, $this->command('POST', '/ezapi/ac/fingerprints', [
            'UsersFingerPrints' => [[
                'UserID' => $userId,
                'FingerPrints' => $rows,
            ]],
        ]));
    }

    /**
     * @param  list<string>  $cards
     */
    public function queueCards(Device $device, string $userId, array $cards): string
    {
        $userId = trim($userId);
        $rows = [];
        foreach (array_values($cards) as $card) {
            $card = trim((string) $card);
            if ($card === '') {
                continue;
            }
            $rows[] = [
                'UserID' => $userId,
                'CardNo' => $card,
                'CardType' => 0,
                'CardName' => $userId,
                'CardStatus' => 0,
            ];
        }
        if ($userId === '' || $rows === []) {
            throw new RuntimeException('A card update needs a user id and a card number.');
        }

        return $this->queue($device, 'card-'.$userId, $this->command('POST', '/ezapi/ac/cards', [
            'CardList' => $rows,
        ]));
    }

    public function queueDeleteUser(Device $device, string $userId): string
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new RuntimeException('This person has no user id.');
        }

        return $this->queue($device, 'delete-'.$userId, $this->command('POST', '/ezapi/ac/users/delete', [
            'UserIDList' => [$userId],
        ]));
    }

    public function queueDeletePerson(Device $device, Employee $employee): string
    {
        return $this->queueDeleteUser($device, (string) $employee->employee_code);
    }

    public function directory(): string
    {
        return $this->pushDir();
    }

    public function actionWaiting(Device $device, string $action): bool
    {
        if ($this->commandQueued($device, $action, ['pending', 'sending'])) {
            return true;
        }

        return str_starts_with($action, 'query-') && $this->commandQueued($device, $action, ['sent'], 180);
    }

    public function queueBiometricQuery(Device $device, string $action, array $userIds): void
    {
        $faces = $action === 'query-faces';
        $uri = match ($action) {
            'query-faces' => '/ezapi/ac/faces/query',
            'query-cards' => '/ezapi/ac/cards/query',
            default => '/ezapi/ac/fingerprints/query',
        };
        $this->queue($device, $action, $this->command('POST', $uri, [
            'Condition' => $faces
                ? ['QueryMode' => 0, 'SimpleQuery' => ['UserIDList' => array_values($userIds)]]
                : ['SimpleQuery' => ['UserIDList' => array_values($userIds)]],
            'Offset' => 0,
            'Count' => 10,
        ]));
    }

    public function listRequestWaiting(Device $device): bool
    {
        return $this->actionWaiting($device, 'query-users') || $this->actionWaiting($device, 'query-persons');
    }

    public function queueUserList(Device $device): void
    {
        $this->queue($device, 'query-users', $this->command('POST', '/ezapi/ac/users/query', [
            'Condition' => ['SimpleQuery' => (object) []],
            'Offset' => 0,
            'Count' => 1000,
        ]));
        $this->queue($device, 'query-persons', $this->command('POST', '/ezapi/ac/persons/query', [
            'Condition' => ['LogicalOperator' => 0],
            'Offset' => 0,
            'Count' => 1000,
        ]));
    }

    public function importInbox(Device $device): int
    {
        $dir = $this->pushDir().DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox';
        if (! is_dir($dir)) {
            return 0;
        }

        $prefix = $this->safe($this->registerId($device)).'__';
        $imported = 0;
        foreach (File::files($dir) as $file) {
            if (! str_starts_with($file->getFilename(), $prefix) || ! str_ends_with($file->getFilename(), '.json')) {
                continue;
            }
            $data = json_decode(File::get($file->getPathname()), true);
            File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'done');
            File::move($file->getPathname(), $dir.DIRECTORY_SEPARATOR.'done'.DIRECTORY_SEPARATOR.$file->getFilename());
            if (! is_array($data)) {
                continue;
            }
            $imported += $this->importResult($device, $data);
        }

        return $imported;
    }

    public function rememberPerson(Device $device, string $userId, string $name, ?int $employeeId): DeviceTerminalUser
    {
        return DeviceTerminalUser::query()->updateOrCreate(
            ['device_id' => $device->id, 'user_id' => $userId],
            ['name' => $name, 'employee_id' => $employeeId, 'synced_at' => null],
        );
    }

    private function importResult(Device $device, array $data): int
    {
        $status = (string) data_get($data, 'Response.StatusCode', '');
        if (! str_starts_with($status, '200')) {
            return 0;
        }

        $body = data_get($data, 'Response.Body');
        if (is_string($body)) {
            $body = json_decode($body, true);
        }
        if (is_array($body) && $this->importCollect($device, $body)) {
            return 0;
        }

        $list = is_array($body) ? ($body['InfoList'] ?? null) : null;
        if (! is_array($list)) {
            return 0;
        }

        $people = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $userId = trim((string) ($item['UserID'] ?? data_get($item, 'User.UserID', '')));
            if ($userId === '') {
                continue;
            }
            $name = trim((string) ($item['UserName'] ?? data_get($item, 'User.UserName', '')));
            $people[$userId] = $name !== '' ? $name : ($people[$userId] ?? $userId);
        }
        if ($people === []) {
            return 0;
        }

        $employees = Employee::query()
            ->where('company_id', $device->company_id)
            ->whereIn('employee_code', array_keys($people))
            ->get()
            ->keyBy('employee_code');

        foreach ($people as $userId => $name) {
            $removed = DeviceTerminalUser::query()
                ->where('device_id', $device->id)
                ->where('user_id', $userId)
                ->whereNotNull('dismissed_at')
                ->exists();
            if ($removed && ! $employees->has($userId)) {
                if (! $this->actionWaiting($device, 'delete-'.$userId)) {
                    $this->queueDeleteUser($device, $userId);
                }
                continue;
            }
            $employee = $employees->get($userId) ?? app(DevicePeople::class)->ensure($device, $userId, $name);
            DeviceTerminalUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'user_id' => $userId],
                [
                    'name' => $name,
                    'employee_id' => $employee?->id,
                    'synced_at' => now(),
                ],
            );
            if ($employee) {
                $employees->put($userId, $employee);
                DeviceUser::query()->updateOrCreate(
                    ['device_id' => $device->id, 'employee_id' => $employee->id],
                    ['is_blocked' => false],
                );
            }
        }

        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $userId = trim((string) ($item['UserID'] ?? data_get($item, 'User.UserID', '')));
            $employee = $userId !== '' ? $employees->get($userId) : null;
            if ($employee) {
                app(DevicePeople::class)->storeItem($employee, $item);
            }
        }

        $roster = collect($list)->contains(fn ($item) => is_array($item) && (isset($item['UserName']) || is_array($item['User'] ?? null)));
        $total = $body['Total'] ?? null;
        if ($roster && is_numeric($total) && (int) $total <= count($list)) {
            DeviceTerminalUser::query()
                ->where('device_id', $device->id)
                ->whereNotNull('dismissed_at')
                ->whereNotIn('user_id', array_keys($people))
                ->delete();
        }

        return count($people);
    }

    private function personBody(string $userId, string $name, string $password = ''): array
    {
        $access = config('dahua.person');
        $holiday = array_values($access['holiday_plan']);
        $schedule = array_values($access['schedule']);
        $doors = array_values($access['doors']);

        return [
            'PersonnelInfo' => [
                'UserID' => $userId,
                'User' => [
                    'UserID' => $userId,
                    'UserName' => $name,
                    'UserType' => (int) $access['user_type'],
                    'UseTime' => (int) $access['use_time'],
                    'IsFirstEnter' => false,
                    'UserStatus' => (int) $access['user_status'],
                    'Authority' => (int) $access['authority'],
                    'CitizenIDNo' => '',
                    'Password' => $password,
                    'Doors' => $doors,
                    'ValidFrom' => (string) $access['valid_from'],
                    'ValidTo' => (string) $access['valid_to'],
                    'SpecialDaysSchedule' => $holiday,
                    'TimeSections' => $schedule,
                    'MultiTimeSections' => [[
                        'Door' => $doors[0] ?? 0,
                        'SpecialDaysSchedule' => $holiday,
                        'TimeSections' => $schedule,
                    ]],
                ],
            ],
        ];
    }

    public function collecting(Device $device): bool
    {
        $active = $this->activeCollect($device);
        if (is_array($active)) {
            $started = (int) ($active['started_at'] ?? 0);
            $keep = max(30, (int) ($active['keep_time'] ?? 900));
            if ($started > 0 && time() <= $started + $keep) {
                return true;
            }
            $this->clearActiveCollect($device);
        }

        return $this->collectCommandWaiting($device);
    }

    public function continueCollect(Device $device): void
    {
        $active = $this->activeCollect($device);
        if ($active) {
            $this->pollCollect($device, $active);
        }
    }

    private function releaseCollect(Device $device): void
    {
        $this->clearActiveCollect($device);
        $path = $this->collectPath($device, 'queue');
        if (is_file($path)) {
            unlink($path);
        }
        foreach (['face', 'finger', 'card'] as $kind) {
            $this->releaseQueuedPrefix($device, 'collect-'.$kind.'-');
        }
    }

    private function collectCommandWaiting(Device $device): bool
    {
        $prefix = $this->safe($this->registerId($device)).'__';
        foreach (['pending', 'sending'] as $folder) {
            $dir = $this->pushDir().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::files($dir) as $file) {
                $name = $file->getFilename();
                if (! str_starts_with($name, $prefix)) {
                    continue;
                }
                if (str_contains($name, '__collect-face-') || str_contains($name, '__collect-finger-') || str_contains($name, '__collect-card-')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sentCollectRecently(Device $device): bool
    {
        $prefix = $this->safe($this->registerId($device)).'__';
        $dir = $this->pushDir().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sent';
        if (! is_dir($dir)) {
            return false;
        }
        $cutoff = time() - 120;
        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if (! str_starts_with($name, $prefix) || $file->getMTime() < $cutoff) {
                continue;
            }
            if (str_contains($name, '__collect-face-') || str_contains($name, '__collect-finger-') || str_contains($name, '__collect-card-')) {
                return true;
            }
        }

        return false;
    }

    private function releaseQueuedPrefix(Device $device, string $actionPrefix): void
    {
        $prefix = $this->safe($this->registerId($device)).'__';
        $needle = '__'.$this->safe($actionPrefix);
        foreach (['pending', 'sending'] as $folder) {
            $dir = $this->pushDir().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::files($dir) as $file) {
                $name = $file->getFilename();
                if (str_starts_with($name, $prefix) && str_contains($name, $needle)) {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    private function importCollect(Device $device, array $body): bool
    {
        $token = trim((string) ($body['Token'] ?? ''));
        if ($token !== '' && ! isset($body['InfoList']) && ! array_key_exists('Status', $body)) {
            $waiting = $this->takeCollect($device);
            if ($waiting) {
                $this->saveActiveCollect($device, $token, $waiting['user_id'], $waiting['kind'], (int) ($body['KeepTime'] ?? 900));
            }
            $this->pollCollect($device, ['token' => $token, 'keep_time' => (int) ($body['KeepTime'] ?? 900), 'started_at' => time()]);

            return true;
        }

        if (! array_key_exists('Status', $body) && ! isset($body['DataInfo']) && ! isset($body['FaceInfo']) && ! isset($body['FingerInfo']) && ! isset($body['CardNumber'])) {
            return false;
        }

        $active = $this->activeCollect($device);
        if (! $active) {
            return true;
        }

        $status = (int) ($body['Status'] ?? 1);
        if ($status === 0) {
            $this->pollCollect($device, $active);

            return true;
        }

        $this->clearActiveCollect($device);
        if ($status !== 1) {
            return true;
        }

        $employee = Employee::query()
            ->where('company_id', $device->company_id)
            ->where('employee_code', $active['user_id'])
            ->first();
        if (! $employee) {
            return true;
        }

        $rows = $body['DataInfo'] ?? null;
        $item = ['PhotoData' => [], 'FingerPrints' => []];
        foreach (is_array($rows) ? $rows : [$body] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['FaceInfo'] ?? '')) !== '') {
                $item['PhotoData'][] = $row['FaceInfo'];
            }
            $finger = trim((string) ($row['FingerInfo'] ?? $row['FingerprintData'] ?? ''));
            if ($finger !== '') {
                $item['FingerPrints'][] = [
                    'FingerprintData' => $finger,
                    'FingerprintID' => $row['FingerprintID'] ?? $row['Type'] ?? count($item['FingerPrints']),
                ];
            }
            $card = trim((string) ($row['CardNumber'] ?? $row['CardNo'] ?? ''));
            if ($card !== '') {
                $item['CardNo'] = $card;
            }
        }
        if ($item['PhotoData'] === [] && $item['FingerPrints'] === [] && trim((string) ($item['CardNo'] ?? '')) === '') {
            return true;
        }

        app(DevicePeople::class)->storeItem($employee, $item);
        try {
            app(EmployeePush::class)->keepCollected($device, $employee, $item);
        } catch (\Throwable) {
        }

        return true;
    }

    /**
     * @param  array{token?: string, keep_time?: int, started_at?: int}  $active
     */
    private function pollCollect(Device $device, array $active): void
    {
        $token = trim((string) ($active['token'] ?? ''));
        $started = (int) ($active['started_at'] ?? 0);
        $keep = max(30, (int) ($active['keep_time'] ?? 900));
        if ($token === '' || $started <= 0 || time() > $started + $keep) {
            $this->clearActiveCollect($device);

            return;
        }

        $action = 'collect-get-'.substr($this->safe($token), 0, 24);
        if ($this->actionWaiting($device, $action)) {
            return;
        }

        $this->queue($device, $action, $this->command('GET', '/ezapi/ac/personCollect/'.$token, ''));
    }

    private function collectPath(Device $device, string $name): string
    {
        return $this->pushDir().DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'collects'.DIRECTORY_SEPARATOR.$this->safe($this->registerId($device)).'__'.$name.'.json';
    }

    private function rememberCollect(Device $device, string $userId, string $kind): void
    {
        $path = $this->collectPath($device, 'queue');
        File::ensureDirectoryExists(dirname($path));
        $queue = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $queue = is_array($queue) ? $queue : [];
        $queue[] = ['user_id' => $userId, 'kind' => $kind];
        file_put_contents($path, json_encode($queue));
    }

    /**
     * @return array{user_id: string, kind: string}|null
     */
    private function takeCollect(Device $device): ?array
    {
        $path = $this->collectPath($device, 'queue');
        $queue = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        if (! is_array($queue) || $queue === []) {
            return null;
        }
        $next = array_shift($queue);
        file_put_contents($path, json_encode(array_values($queue)));

        return is_array($next) && isset($next['user_id'], $next['kind']) ? $next : null;
    }

    private function saveActiveCollect(Device $device, string $token, string $userId, string $kind, int $keepTime = 900): void
    {
        $path = $this->collectPath($device, 'active');
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, json_encode([
            'token' => $token,
            'user_id' => $userId,
            'kind' => $kind,
            'keep_time' => max(30, $keepTime),
            'started_at' => time(),
        ]));
        $this->releaseQueued($device, 'collect-'.$kind.'-'.$userId);
    }

    /**
     * @return array{token: string, user_id: string, kind: string}|null
     */
    private function activeCollect(Device $device): ?array
    {
        $path = $this->collectPath($device, 'active');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) && isset($data['user_id']) ? $data : null;
    }

    private function clearActiveCollect(Device $device): void
    {
        $path = $this->collectPath($device, 'active');
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function command(string $method, string $uri, mixed $body): array
    {
        return [
            'Request' => [
                'Index' => 1,
                'URI' => $uri,
                'Method' => $method,
                'Headers' => ['Content-Type' => 'application/json'],
                'Body' => $body,
            ],
        ];
    }

    private function commandQueued(Device $device, string $action, array $folders, ?int $withinSeconds = null): bool
    {
        $prefix = $this->safe($this->registerId($device)).'__';
        $suffix = '__'.$this->safe($action).'.json';
        $cutoff = $withinSeconds === null ? null : time() - $withinSeconds;
        foreach ($folders as $folder) {
            $dir = $this->pushDir().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::files($dir) as $file) {
                $name = $file->getFilename();
                if (! str_starts_with($name, $prefix) || ! str_ends_with($name, $suffix)) {
                    continue;
                }
                if ($cutoff !== null && $file->getMTime() < $cutoff) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private function releaseQueued(Device $device, string $action): void
    {
        $prefix = $this->safe($this->registerId($device)).'__';
        $suffix = '__'.$this->safe($action).'.json';
        foreach (['pending', 'sending'] as $folder) {
            $dir = $this->pushDir().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.$folder;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::files($dir) as $file) {
                $name = $file->getFilename();
                if (str_starts_with($name, $prefix) && str_ends_with($name, $suffix)) {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    private function queue(Device $device, string $action, array $payload): string
    {
        $dir = $this->pushDir().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending';
        File::ensureDirectoryExists($dir);

        $this->queueSequence = ($this->queueSequence + 1) % 100;
        $name = $this->safe($this->registerId($device)).'__'.now()->format('YmdHisv').sprintf('%02d', $this->queueSequence).'__'.$this->safe($action).'.json';
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode the terminal command.');
        }

        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException('Could not write the terminal command.');
        }

        return $path;
    }

    private function pushDir(): string
    {
        $configured = rtrim((string) config('dahua.push_dir'), '\\/');
        if ($configured !== '' && ! $this->foreignPath($configured)) {
            if (is_dir($configured) || @mkdir($configured, 0755, true) || is_dir($configured)) {
                return $configured;
            }
        }

        $fallback = storage_path('app/dahua');
        if (! is_dir($fallback)) {
            mkdir($fallback, 0755, true);
        }

        return $fallback;
    }

    private function foreignPath(string $path): bool
    {
        return PHP_OS_FAMILY !== 'Windows' && preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function safe(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $value) ?: 'device';

        return $safe;
    }
}
