<?php

namespace App\Services\Devices;

use App\Support\StoredImage;
use Carbon\Carbon;
use RuntimeException;

class HikvisionEvents
{
    public function __construct(private readonly HikvisionClient $client) {}

    /**
     * A face or finger scan posted by the terminal itself. Heartbeats, door
     * events, and failed reads return null.
     *
     * @return array{serial: string, user_id: string, name: string, utc: int, type: string, image: ?string, ip: string, device_name: string, attendance: string}|null
     */
    public function fromPush(string $body): ?array
    {
        $jpegAt = strpos($body, "\xFF\xD8\xFF");
        $payload = $this->jsonObject($jpegAt === false ? $body : substr($body, 0, $jpegAt));
        if ($payload === null) {
            return null;
        }
        if (strcasecmp((string) ($payload['eventType'] ?? ''), 'heartBeat') === 0) {
            return null;
        }

        $event = $payload['AccessControllerEvent'] ?? null;
        if (is_string($event)) {
            $decoded = json_decode($event, true);
            $event = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($event)) {
            $event = isset($payload['subEventType']) || isset($payload['employeeNoString']) ? $payload : null;
        }
        if (! is_array($event)) {
            return null;
        }

        $minor = $event['subEventType'] ?? $event['minor'] ?? -1;
        if (is_string($minor) && str_starts_with(strtolower(trim($minor)), '0x')) {
            $minor = hexdec($minor);
        }
        $row = [
            'minor' => (int) $minor,
            'currentVerifyMode' => (string) ($event['currentVerifyMode'] ?? ''),
            'attendanceStatus' => (string) ($event['attendanceStatus'] ?? ''),
            'employeeNoString' => (string) ($event['employeeNoString'] ?? $event['employeeNo'] ?? ''),
            'name' => (string) ($event['name'] ?? ''),
            'serialNo' => $event['serialNo'] ?? 0,
            'time' => (string) ($payload['dateTime'] ?? $event['dateTime'] ?? $event['time'] ?? ''),
        ];
        if (! $this->passed($row)) {
            return null;
        }

        $userId = trim($row['employeeNoString']);
        $time = trim($row['time']);
        if ($userId === '' || $time === '') {
            return null;
        }
        try {
            $utc = Carbon::parse($time, 'Asia/Kolkata')->utc()->timestamp;
        } catch (\Throwable) {
            return null;
        }

        $serial = (int) $row['serialNo'];
        $name = trim($row['name']);
        $image = null;
        if ($jpegAt !== false) {
            $jpeg = substr($body, $jpegAt);
            $end = strpos($jpeg, "\xFF\xD9");
            if ($end !== false) {
                $jpeg = substr($jpeg, 0, $end + 2);
            }
            if (strlen($jpeg) <= 2_000_000) {
                $image = StoredImage::normalize(base64_encode($jpeg));
            }
        }
        if ($image === null) {
            foreach (['pictureData', 'PicData', 'facePic'] as $key) {
                $inline = $event[$key] ?? null;
                if (is_string($inline) && $inline !== '') {
                    $image = StoredImage::normalize($inline);
                    break;
                }
            }
        }

        return [
            'serial' => $serial > 0 ? (string) $serial : substr(sha1($userId.'|'.$utc), 0, 16),
            'user_id' => $userId,
            'name' => $name !== '' ? $name : $userId,
            'utc' => $utc,
            'type' => $this->type($row),
            'image' => $image,
            'ip' => trim((string) ($payload['ipAddress'] ?? '')),
            'device_name' => trim((string) ($event['deviceName'] ?? $payload['deviceName'] ?? '')),
            'attendance' => strtolower(trim((string) ($row['attendanceStatus'] ?? ''))),
        ];
    }

    /**
     * @return list<array{serial: string, user_id: string, name: string, utc: int, type: string, image: ?string}>
     */
    public function faceScans(string $host, string $username, string $password, Carbon $from, Carbon $to): array
    {
        $base = $this->baseUrl($host);
        $position = 0;
        $withOffset = true;
        $pages = 0;
        $scans = [];

        while ($pages < 20) {
            $pages++;
            $response = $this->client->call(
                'POST',
                $base.'/ISAPI/AccessControl/AcsEvent?format=json',
                $username,
                $password,
                $this->searchBody($from, $to, $position, $withOffset),
            );
            if ($response['status'] === 401) {
                throw new HikvisionAuthException('Hikvision login was refused for '.$base.'.');
            }

            $json = json_decode($response['body'], true);
            $event = is_array($json) ? ($json['AcsEvent'] ?? null) : null;
            if (! is_array($event) && $withOffset && $position === 0 && $response['status'] !== 401) {
                $withOffset = false;
                $pages--;

                continue;
            }
            if ($response['status'] !== 200 || ! is_array($event)) {
                $detail = is_array($json) ? (string) ($json['errorMsg'] ?? $json['statusString'] ?? '') : '';
                throw new RuntimeException(trim('Hikvision events returned HTTP '.$response['status'].' '.$detail));
            }

            foreach ($this->rows($event) as $row) {
                $scan = $this->mapRow($row, $base, $username, $password, $from, $to);
                if ($scan) {
                    $scans[] = $scan;
                }
            }

            $count = (int) ($event['numOfMatches'] ?? 0);
            if (($event['responseStatusStrg'] ?? '') !== 'MORE' || $count < 1) {
                break;
            }
            $position += $count;
        }

        usort($scans, fn (array $left, array $right) => $left['utc'] <=> $right['utc']);

        return $scans;
    }

    /**
     * People whose terminal record starts today. A new enrollment uses today's date.
     *
     * @return list<array{user_id: string, name: string, face_url: string}>
     */
    public function enrolledOn(string $host, string $username, string $password, Carbon $day): array
    {
        $base = $this->baseUrl($host);
        $wanted = $day->timezone('Asia/Kolkata')->toDateString();
        $found = [];
        foreach ($this->users($base, $username, $password) as $person) {
            $begin = trim((string) data_get($person, 'Valid.beginTime'));
            $userId = trim((string) ($person['employeeNo'] ?? ''));
            if ($userId === '' || $begin === '') {
                continue;
            }
            try {
                $starts = Carbon::parse($begin, 'Asia/Kolkata')->toDateString();
            } catch (\Throwable) {
                continue;
            }
            if ($starts !== $wanted) {
                continue;
            }
            $name = trim((string) ($person['name'] ?? ''));
            $found[] = [
                'user_id' => $userId,
                'name' => $name !== '' ? $name : $userId,
                'face_url' => trim((string) ($person['faceURL'] ?? '')),
            ];
        }

        return $found;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonObject(string $body): ?array
    {
        $start = strpos($body, '{');
        if ($start === false) {
            return null;
        }
        $json = substr($body, $start);
        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($json);
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }
                if ($char === '\\') {
                    $escape = true;

                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }
            if ($char === '"') {
                $inString = true;

                continue;
            }
            if ($char === '{') {
                $depth++;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode(substr($json, 0, $i + 1), true);

                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return list<array<string, mixed>>
     */
    private function rows(array $event): array
    {
        $list = $event['InfoList'] ?? [];
        if (! is_array($list)) {
            return [];
        }
        if (isset($list['time']) || isset($list['employeeNoString']) || isset($list['serialNo'])) {
            return [$list];
        }

        return array_values(array_filter($list, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{serial: string, user_id: string, name: string, utc: int, type: string, image: ?string}|null
     */
    private function mapRow(array $row, string $base, string $username, string $password, Carbon $from, Carbon $to): ?array
    {
        if (! $this->passed($row)) {
            return null;
        }

        $userId = trim((string) ($row['employeeNoString'] ?? ''));
        if ($userId === '' && isset($row['employeeNo'])) {
            $userId = trim((string) $row['employeeNo']);
        }
        $time = trim((string) ($row['time'] ?? ''));
        if ($userId === '' || $time === '') {
            return null;
        }

        try {
            $at = Carbon::parse($time, 'Asia/Kolkata');
        } catch (\Throwable) {
            return null;
        }
        $utc = $at->utc()->timestamp;
        if ($utc < $from->utc()->timestamp || $utc > $to->copy()->addMinutes(10)->utc()->timestamp) {
            return null;
        }

        $serial = (int) ($row['serialNo'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));

        return [
            'serial' => $serial > 0 ? (string) $serial : substr(sha1($userId.'|'.$utc), 0, 16),
            'user_id' => $userId,
            'name' => $name !== '' ? $name : $userId,
            'utc' => $utc,
            'type' => $this->type($row),
            'image' => $this->image($row, $base, $username, $password),
        ];
    }

    /**
     * A successful face or fingerprint. Card swipes and failed reads stay out.
     *
     * @param  array<string, mixed>  $row
     */
    private function passed(array $row): bool
    {
        $minor = (int) ($row['minor'] ?? -1);
        $mode = strtolower((string) ($row['currentVerifyMode'] ?? ''));
        if (str_contains($mode, 'fail') || str_contains($mode, 'invalid')) {
            return false;
        }
        if (in_array($minor, [6, 7, 8, 9, 39, 76, 77, 78, 79, 80], true)) {
            return false;
        }
        if ($minor === 75 || $minor === 38) {
            return true;
        }

        return $minor === 1
            && $mode !== ''
            && (str_contains($mode, 'finger') || preg_match('/(^|[^a-z])fp([^a-z]|$)/', $mode) === 1);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function type(array $row): string
    {
        $status = strtolower((string) ($row['attendanceStatus'] ?? ''));

        return in_array($status, ['checkout', 'breakout', 'overtimeout'], true) ? 'Exit' : 'Entry';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function image(array $row, string $base, string $username, string $password): ?string
    {
        foreach (['pictureData', 'PicData', 'facePic'] as $key) {
            $inline = $row[$key] ?? null;
            if (is_string($inline) && $inline !== '') {
                return StoredImage::normalize($inline);
            }
        }

        $url = trim((string) ($row['pictureURL'] ?? ''));
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/')) {
            $url = $base.$url;
        }

        try {
            $response = $this->client->call('GET', $url, $username, $password);
        } catch (\Throwable) {
            return null;
        }
        if ($response['status'] !== 200 || $response['body'] === '' || strlen($response['body']) > 2_000_000) {
            return null;
        }

        return StoredImage::normalize(base64_encode($response['body']));
    }

    /**
     * @return array<string, mixed>
     */
    private function searchBody(Carbon $from, Carbon $to, int $position, bool $withOffset): array
    {
        $pattern = $withOffset ? 'Y-m-d\TH:i:sP' : 'Y-m-d\TH:i:s';

        return [
            'AcsEventCond' => [
                'searchID' => 'hrms'.substr(sha1($position.'|'.$from->timestamp), 0, 8),
                'searchResultPosition' => $position,
                'maxResults' => 30,
                'major' => 5,
                'minor' => 0,
                'startTime' => $from->timezone('Asia/Kolkata')->format($pattern),
                'endTime' => $to->timezone('Asia/Kolkata')->format($pattern),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function users(string $base, string $username, string $password): array
    {
        $position = 0;
        $pages = 0;
        $people = [];
        while ($pages < 40) {
            $pages++;
            $response = $this->client->call('POST', $base.'/ISAPI/AccessControl/UserInfo/Search?format=json', $username, $password, [
                'UserInfoSearchCond' => [
                    'searchID' => 'hrms'.substr(sha1((string) $position), 0, 8),
                    'searchResultPosition' => $position,
                    'maxResults' => 30,
                ],
            ]);
            if ($response['status'] === 401) {
                throw new HikvisionAuthException('Hikvision login was refused for '.$base.'.');
            }
            $json = json_decode($response['body'], true);
            $search = is_array($json) ? ($json['UserInfoSearch'] ?? null) : null;
            if ($response['status'] !== 200 || ! is_array($search)) {
                break;
            }
            $list = $search['UserInfo'] ?? [];
            if (isset($list['employeeNo'])) {
                $list = [$list];
            }
            foreach ($list as $person) {
                if (is_array($person)) {
                    $people[] = $person;
                }
            }
            $count = (int) ($search['numOfMatches'] ?? 0);
            if (($search['responseStatusStrg'] ?? '') !== 'MORE' || $count < 1) {
                break;
            }
            $position += $count;
        }

        return $people;
    }

    /**
     * Employee code to terminal person type. blackList cannot scan.
     *
     * @return array<string, string>
     */
    public function userTypes(string $host, string $username, string $password): array
    {
        $types = [];
        foreach ($this->users($this->baseUrl($host), $username, $password) as $person) {
            $code = trim((string) ($person['employeeNo'] ?? ''));
            if ($code === '') {
                continue;
            }
            $types[$code] = (string) ($person['userType'] ?? 'normal');
        }

        return $types;
    }

    public function alignClock(string $host, string $username, string $password): void
    {
        $zone = $this->clockZone();
        $local = now()->timezone($zone['php'])->format('Y-m-d\TH:i:sP');
        $base = $this->baseUrl($host);
        try {
            $response = $this->client->call('GET', $base.'/ISAPI/System/time', $username, $password);
        } catch (\Throwable) {
            return;
        }
        if ($response['status'] !== 200) {
            return;
        }

        $deviceZone = '';
        $deviceTime = '';
        if (preg_match('/<timeZone>([^<]*)<\/timeZone>/', $response['body'], $zoneMatch)) {
            $deviceZone = trim($zoneMatch[1]);
        }
        if (preg_match('/<localTime>([^<]+)<\/localTime>/', $response['body'], $timeMatch)) {
            $deviceTime = $timeMatch[1];
        }
        $close = false;
        if ($deviceTime !== '') {
            try {
                $close = abs(Carbon::parse($deviceTime)->getTimestamp() - now()->getTimestamp()) <= 20;
            } catch (\Throwable) {
                $close = false;
            }
        }
        if ($close && $this->sameClockZone($deviceZone, $zone['device'])) {
            return;
        }

        $xml = '<Time version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<timeMode>manual</timeMode><localTime>'.$local.'</localTime><timeZone>'.$zone['device'].'</timeZone></Time>';
        try {
            $this->client->call('PUT', $base.'/ISAPI/System/time', $username, $password, $xml, 12, 'application/xml');
        } catch (\Throwable) {
            return;
        }
    }

    /**
     * The application timezone, and the same offset in the terminal's own zone text.
     * These terminals store +05:30 as CST-5:30:00.
     *
     * @return array{php: string, device: string}
     */
    public function clockZone(): array
    {
        $php = (string) config('app.timezone', 'Asia/Kolkata');
        $minutes = now()->timezone($php)->offsetMinutes;
        $sign = $minutes >= 0 ? '-' : '+';
        $abs = abs($minutes);

        return [
            'php' => $php,
            'device' => sprintf('CST%s%d:%02d:00', $sign, intdiv($abs, 60), $abs % 60),
        ];
    }

    private function sameClockZone(string $device, string $wanted): bool
    {
        $normal = function (string $value): string {
            if (! preg_match('/^CST([+-])(\d+):(\d+)/', trim($value), $match)) {
                return trim($value);
            }

            return sprintf('CST%s%d:%02d', $match[1], (int) $match[2], (int) $match[3]);
        };

        return $normal($device) === $normal($wanted);
    }

    /**
     * Names and codes on the terminal. Passwords are never included.
     *
     * @return list<array{code: string, name: string}>
     */
    public function directory(string $host, string $username, string $password): array
    {
        $people = [];
        foreach ($this->users($this->baseUrl($host), $username, $password) as $person) {
            $code = trim((string) ($person['employeeNo'] ?? ''));
            if ($code === '') {
                continue;
            }
            $name = trim((string) ($person['name'] ?? ''));
            $people[] = ['code' => $code, 'name' => $name !== '' ? $name : $code];
        }

        return $people;
    }

    public function userCount(string $host, string $username, string $password): ?int
    {
        try {
            $response = $this->client->call('GET', $this->baseUrl($host).'/ISAPI/AccessControl/UserInfo/Count?format=json', $username, $password);
        } catch (\Throwable) {
            return null;
        }
        if ($response['status'] !== 200) {
            return null;
        }
        $number = data_get(json_decode($response['body'], true), 'UserInfoCount.userNumber');

        return is_numeric($number) ? (int) $number : null;
    }

    /**
     * @return list<array{code: string, name: string, faces: int, fingers: int}>
     */
    public function biometrics(string $host, string $username, string $password): array
    {
        $rows = [];
        foreach ($this->users($this->baseUrl($host), $username, $password) as $person) {
            $code = trim((string) ($person['employeeNo'] ?? ''));
            if ($code === '') {
                continue;
            }
            $name = trim((string) ($person['name'] ?? ''));
            $rows[] = [
                'code' => $code,
                'name' => $name !== '' ? $name : $code,
                'faces' => (int) ($person['numOfFace'] ?? 0),
                'fingers' => (int) ($person['numOfFP'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, data: string}>
     */
    public function fingerprints(string $host, string $username, string $password, string $userId, int $expected): array
    {
        $base = $this->baseUrl($host);
        $found = [];
        $want = max(1, $expected);
        for ($id = 1; $id <= 10 && count($found) < $want; $id++) {
            $response = $this->client->call('POST', $base.'/ISAPI/AccessControl/FingerPrintUpload?format=json', $username, $password, [
                'FingerPrintCond' => [
                    'searchID' => 'hrms'.substr(sha1($userId.'|'.$id), 0, 8),
                    'employeeNo' => $userId,
                    'fingerPrintID' => $id,
                ],
            ]);
            $list = data_get(json_decode($response['body'], true), 'FingerPrintInfo.FingerPrintList', []);
            if (isset($list['fingerPrintID'])) {
                $list = [$list];
            }
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $data = trim((string) ($row['fingerData'] ?? ''));
                $fingerId = (int) ($row['fingerPrintID'] ?? 0);
                if ($data === '' || $fingerId < 1) {
                    continue;
                }
                $found[$fingerId] = ['id' => $fingerId, 'data' => $data];
            }
        }

        return array_values($found);
    }

    /**
     * The face picture and the device model from the terminal that already matches.
     *
     * @return array{jpeg: ?string, model: string}
     */
    public function faceTemplate(string $host, string $username, string $password, string $userId): array
    {
        $base = $this->baseUrl($host);
        $empty = ['jpeg' => null, 'model' => ''];
        try {
            $response = $this->client->call('POST', $base.'/ISAPI/Intelligent/FDLib/FDSearch?format=json', $username, $password, [
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'faceLibType' => 'blackFD',
                'FDID' => '1',
                'FPID' => $userId,
            ]);
        } catch (\Throwable) {
            return $empty;
        }
        $json = $this->jsonObject($response['body']);
        $row = is_array($json) ? data_get($json, 'MatchList.0') : null;
        if (! is_array($row)) {
            return $empty;
        }
        $model = trim((string) ($row['modelData'] ?? ''));
        $url = trim((string) ($row['faceURL'] ?? ''));
        $jpeg = null;
        if ($url !== '') {
            if (str_starts_with($url, '/')) {
                $url = $base.$url;
            } elseif (preg_match('#^https?://#', $url) === 1) {
                $pathAt = strpos($url, '/', 8);
                $url = $pathAt === false ? $base : $base.substr($url, $pathAt);
            }
            try {
                $photo = $this->client->call('GET', $url, $username, $password);
            } catch (\Throwable) {
                $photo = null;
            }
            if (is_array($photo) && (int) ($photo['status'] ?? 0) === 200) {
                $body = (string) ($photo['body'] ?? '');
                if (! str_starts_with($body, "\xFF\xD8\xFF")) {
                    $decoded = base64_decode($body, true);
                    $body = is_string($decoded) ? $decoded : '';
                }
                if (str_starts_with($body, "\xFF\xD8\xFF") && strlen($body) <= 2_000_000) {
                    $jpeg = $body;
                }
            }
        }

        return ['jpeg' => $jpeg, 'model' => $model];
    }

    public function facePhoto(string $host, string $username, string $password, string $userId, string $faceUrl = ''): ?string
    {
        return $this->enrolledFace($this->baseUrl($host), $username, $password, $userId, ['faceURL' => $faceUrl]);
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function enrolledFace(string $base, string $username, string $password, string $userId, array $person): ?string
    {
        $url = trim((string) ($person['faceURL'] ?? ''));
        if ($url === '') {
            $response = $this->client->call('POST', $base.'/ISAPI/Intelligent/FDLib/FDSearch?format=json', $username, $password, [
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'faceLibType' => 'blackFD',
                'FDID' => '1',
                'FPID' => $userId,
            ]);
            $json = json_decode($response['body'], true);
            $url = trim((string) (is_array($json) ? data_get($json, 'MatchList.0.faceURL') : ''));
        }
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/')) {
            $url = $base.$url;
        }

        try {
            $photo = $this->client->call('GET', $url, $username, $password);
        } catch (\Throwable) {
            return null;
        }
        if ($photo['status'] !== 200 || $photo['body'] === '' || strlen($photo['body']) > 2_000_000) {
            return null;
        }

        return StoredImage::normalize(base64_encode($photo['body']));
    }

    private function baseUrl(string $host): string
    {
        $host = trim($host);
        if (! str_starts_with($host, 'http://') && ! str_starts_with($host, 'https://')) {
            $host = 'http://'.$host;
        }

        return rtrim($host, '/');
    }
}
