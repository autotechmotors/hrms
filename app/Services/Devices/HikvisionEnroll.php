<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceTerminalUser;
use App\Models\Employee;
use App\Models\EmployeeCredential;
use App\Support\StoredImage;
use RuntimeException;

class HikvisionEnroll
{
    public function __construct(
        private readonly HikvisionClient $client,
        private readonly DevicePeople $people,
    ) {}

    public function capture(Device $device, Employee $employee, string $kind, ?string $password = null): void
    {
        $userId = trim((string) $employee->employee_code);
        $name = trim($employee->full_name);
        if ($userId === '' || $name === '') {
            throw new RuntimeException('This employee needs a code and a name before the face terminal can store them.');
        }

        if ($kind === 'password') {
            $this->ensureUser($device, $userId, $name, $password);

            return;
        }

        $linked = DeviceTerminalUser::query()
            ->where('device_id', $device->id)
            ->where('user_id', $userId)
            ->where('employee_id', $employee->id)
            ->exists();
        if (! $linked) {
            $this->ensureUser($device, $userId, $name, null, false);
        }
        if ($kind === 'face') {
            $jpeg = $this->takenFace($device, $userId, $name);
            $next = 0;
            foreach (EmployeeCredential::query()->where('employee_id', $employee->id)->where('kind', 'face')->pluck('slot') as $slot) {
                $next = max($next, (int) $slot + 1);
            }
            $this->people->storeItem($employee, ['PhotoData' => [['slot' => $next, 'data' => base64_encode($jpeg)]]]);

            return;
        }
        if ($kind === 'finger') {
            $used = EmployeeCredential::query()
                ->where('employee_id', $employee->id)
                ->where('kind', 'finger')
                ->pluck('slot')
                ->map(fn ($slot) => (int) $slot)
                ->all();
            $fingerNo = 0;
            for ($number = 1; $number <= 10; $number++) {
                if (! in_array($number, $used, true)) {
                    $fingerNo = $number;
                    break;
                }
            }
            $data = $this->takenFinger($device, $userId, $name, $fingerNo);
            $this->people->storeItem($employee, ['FingerPrints' => [['FingerprintID' => $fingerNo, 'FingerprintData' => $data]]]);

            return;
        }

        throw new RuntimeException($device->name.' adds a face or a finger. Stand at that terminal and use those buttons.');
    }

    /**
     * @param  list<string>  $prints
     */
    public function storeProfile(Device $device, string $userId, string $name, ?string $password, ?string $face, array $prints, bool $enabled = true): void
    {
        $this->ensureUser($device, $userId, $name, $password, true, $enabled);
        $jpeg = $this->jpegBytes($face);
        if ($jpeg !== null) {
            $this->uploadJpeg($device, $userId, $jpeg);
        }
        foreach (array_values($prints) as $index => $print) {
            $this->saveFinger($device, $userId, $index + 1, $print);
        }
    }

    public function remove(Device $device, string $userId): void
    {
        [$username, $password] = $this->login($device);
        $response = $this->client->call('PUT', $this->base($device).'/ISAPI/AccessControl/UserInfo/Delete?format=json', $username, $password, [
            'UserInfoDelCond' => ['EmployeeNoList' => [['employeeNo' => $userId]]],
        ]);
        if (! $this->ok($response) && ! $this->missing($response)) {
            throw new RuntimeException($this->problem($response));
        }
    }

    public function deleteFace(Device $device, string $userId): void
    {
        [$username, $password] = $this->login($device);
        $response = $this->client->call('PUT', $this->base($device).'/ISAPI/Intelligent/FDLib/FDSearch/Delete?format=json&FDID=1&faceLibType=blackFD', $username, $password, [
            'FPID' => [['value' => $userId]],
        ]);
        if ($this->ok($response) || $this->missing($response)) {
            return;
        }

        throw new RuntimeException($this->problem($response));
    }

    public function deleteFinger(Device $device, string $userId, int $fingerNo): void
    {
        [$username, $password] = $this->login($device);
        $response = $this->client->call('PUT', $this->base($device).'/ISAPI/AccessControl/FingerPrint/Delete?format=json', $username, $password, [
            'FingerPrintDelete' => [
                'mode' => 'byEmployeeNo',
                'EmployeeNoDetail' => [
                    'employeeNo' => $userId,
                    'fingerPrintID' => [$fingerNo],
                ],
            ],
        ]);
        if ($this->ok($response) || $this->missing($response)) {
            return;
        }

        throw new RuntimeException($this->problem($response));
    }

    public function replaceFace(Device $device, string $userId, string $jpeg): void
    {
        $this->uploadJpeg($device, $userId, $jpeg);
    }

    public function published(Device $device): bool
    {
        if ((int) $device->port > 0 && trim((string) $device->ip_address) !== '') {
            return true;
        }
        $port = config('hikvision.port');

        return trim((string) config('hikvision.host')) !== '' && is_numeric($port) && (int) $port > 0;
    }

    public function reachable(Device $device): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }
        if (config('hikvision.direct') === false) {
            return false;
        }
        if ($device->terminalPassword() === '' && trim((string) config('hikvision.password')) === '') {
            return false;
        }

        [$host, $port] = $this->endpoint($device);
        if ($host === '') {
            return false;
        }
        $socket = @fsockopen($host, $port, $errno, $error, 2);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    public function takenFace(Device $device, string $userId, string $name): string
    {
        return $this->captureFace($device, $userId);
    }

    public function takenFinger(Device $device, string $userId, string $name, int $fingerNo): string
    {
        if ($fingerNo < 1 || $fingerNo > 10) {
            throw new RuntimeException($device->name.' already has 10 fingers for this person.');
        }

        return $this->captureFinger($device, $userId, $fingerNo);
    }

    public function ensureUser(Device $device, string $userId, string $name, ?string $password = null, bool $refresh = true, bool $enabled = true): void
    {
        [$username, $secret] = $this->login($device);
        $body = $this->userBody($userId, $name, $password, $enabled, 'normal');
        $base = $this->base($device);
        $created = $this->client->call('POST', $base.'/ISAPI/AccessControl/UserInfo/Record?format=json', $username, $secret, $body);
        if ($this->ok($created)) {
            return;
        }
        if (! $this->exists($created)) {
            throw new RuntimeException($this->problem($created));
        }
        if (! $refresh) {
            return;
        }

        $updated = $this->client->call('PUT', $base.'/ISAPI/AccessControl/UserInfo/Modify?format=json', $username, $secret, $body);
        if (! $this->ok($updated)) {
            throw new RuntimeException($this->problem($updated));
        }
    }

    public function setEnabled(Device $device, string $userId, string $name, bool $enabled): void
    {
        [$username, $secret] = $this->login($device);
        $updated = $this->client->call(
            'PUT',
            $this->base($device).'/ISAPI/AccessControl/UserInfo/Modify?format=json',
            $username,
            $secret,
            $this->userBody($userId, $name, null, $enabled),
        );
        if (! $this->ok($updated)) {
            throw new RuntimeException($this->problem($updated));
        }
    }

    /**
     * OUT terminal person type. blackList is the device "Blocklist" user.
     * normal is a regular user who can match for the out window or an approved pass.
     */
    public function setUserType(Device $device, string $userId, string $name, string $userType): void
    {
        $userType = $userType === 'blackList' ? 'blackList' : 'normal';
        [$username, $secret] = $this->login($device);
        $body = $this->userBody($userId, $name, null, true, $userType);
        $base = $this->base($device);
        $updated = $this->client->call('PUT', $base.'/ISAPI/AccessControl/UserInfo/Modify?format=json', $username, $secret, $body);
        if ($this->ok($updated)) {
            return;
        }
        $created = $this->client->call('POST', $base.'/ISAPI/AccessControl/UserInfo/Record?format=json', $username, $secret, $body);
        if (! $this->ok($created) && ! $this->exists($created)) {
            throw new RuntimeException($this->problem($updated));
        }
    }

    private function captureFace(Device $device, string $userId): string
    {
        [$username, $password] = $this->login($device);
        $base = $this->base($device);
        $types = $this->faceDataTypes($device);
        $plans = [];
        foreach ($types as $type) {
            $plans[] = [$type, false];
        }
        $plans[] = ['binary', true];
        $response = ['status' => 0, 'body' => ''];
        foreach ($plans as [$type, $withReader]) {
            $inner = '<captureInfrared>false</captureInfrared><dataType>'.$type.'</dataType>';
            if ($withReader) {
                $inner .= '<readerID>1</readerID>';
            }
            $response = $this->client->call(
                'POST',
                $base.'/ISAPI/AccessControl/CaptureFaceData',
                $username,
                $password,
                $this->xml('CaptureFaceDataCond', $inner),
                55,
            );
            $jpeg = $this->jpegIn($response['body']);
            if ($jpeg !== null) {
                $this->uploadJpeg($device, $userId, $jpeg);

                return $jpeg;
            }
            if ($this->faceCaptureReady($response['body'])) {
                break;
            }
            if (! $this->ok($response) && ! $this->badFaceDataType($response)) {
                throw new RuntimeException($this->problem($response).' ('.$device->terminalHost().')');
            }
        }

        $url = $this->value($response['body'], 'faceDataUrl');
        if ($url === '') {
            $url = $this->value($response['body'], 'faceURL');
        }
        if ($url === '') {
            $progress = $this->value($response['body'], 'captureProgress');
            $detail = $progress === '' ? 'no photo' : 'progress '.$progress;

            throw new RuntimeException($device->name.' at '.$device->terminalHost().' returned '.$detail.'. Stand in front of that camera until the green frame finishes, then try again.');
        }
        $url = $this->publicPhotoUrl($url, $base);

        $photo = $this->client->call('GET', $url, $username, $password, null, 15);
        $jpeg = $photo['status'] === 200 ? $photo['body'] : '';
        if ($jpeg === '' || ! str_starts_with($jpeg, "\xFF\xD8\xFF")) {
            throw new RuntimeException('The face terminal took a picture, but HRMS could not read it.');
        }

        $this->bindFace($device, $userId, $url, $jpeg);

        return $jpeg;
    }

    /**
     * @return list<string>
     */
    private function faceDataTypes(Device $device): array
    {
        try {
            [$username, $password] = $this->login($device);
            $caps = $this->client->call('GET', $this->base($device).'/ISAPI/AccessControl/CaptureFaceData/capabilities', $username, $password, null, 5);
            if ($this->ok($caps) || $caps['status'] === 200) {
                if (preg_match('/<dataType[^>]*opt="([^"]+)"/', $caps['body'], $match) === 1) {
                    $options = array_values(array_filter(array_map('trim', explode(',', strtolower($match[1])))));
                    if ($options !== []) {
                        $preferred = array_values(array_intersect(['url', 'binary'], $options));

                        return $preferred !== [] ? $preferred : $options;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return ['url', 'binary'];
    }

    private function badFaceDataType(array $response): bool
    {
        $body = strtolower($response['body']);

        return str_contains($body, 'badparameters')
            || str_contains($body, 'invalid content')
            || str_contains($body, 'invalid operation')
            || str_contains($body, 'notsupport');
    }

    private function jpegIn(string $body): ?string
    {
        $start = strpos($body, "\xFF\xD8\xFF");
        if ($start !== false) {
            $end = strpos($body, "\xFF\xD9", $start);
            $jpeg = $end === false ? substr($body, $start) : substr($body, $start, $end - $start + 2);

            return $jpeg !== '' ? $jpeg : null;
        }
        $inline = $this->value($body, 'faceData');
        if ($inline === '') {
            return null;
        }
        $jpeg = base64_decode(preg_replace('/\s+/', '', $inline) ?? '', true) ?: '';

        return str_starts_with($jpeg, "\xFF\xD8\xFF") ? $jpeg : null;
    }

    private function publicPhotoUrl(string $url, string $base): string
    {
        if (str_starts_with($url, '/')) {
            return $base.$url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return $url;
        }
        $private = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        if (! $private) {
            return $url;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return $base.$path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }

    private function faceCaptureReady(string $body): bool
    {
        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return true;
        }
        if ($this->value($body, 'faceData') !== '' || $this->value($body, 'faceDataUrl') !== '' || $this->value($body, 'faceURL') !== '') {
            return true;
        }
        $progress = $this->value($body, 'captureProgress');

        return $progress !== '' && (int) $progress >= 100;
    }

    private function captureFinger(Device $device, string $userId, int $fingerNo): string
    {
        [$username, $password] = $this->login($device);
        $response = $this->client->call('POST', $this->base($device).'/ISAPI/AccessControl/CaptureFingerPrint', $username, $password, $this->xml('CaptureFingerPrintCond', '<fingerNo>'.$fingerNo.'</fingerNo>'), 55);
        if (! $this->ok($response)) {
            throw new RuntimeException($this->problem($response));
        }

        $data = $this->value($response['body'], 'fingerData');
        if ($data === '') {
            throw new RuntimeException('Place the finger on '.$device->name.' and hold it until the terminal finishes.');
        }

        $this->saveFinger($device, $userId, $fingerNo, $data);

        return $data;
    }

    private function bindFace(Device $device, string $userId, string $url, string $jpeg): void
    {
        [$username, $password] = $this->login($device);
        $linked = $this->client->call('POST', $this->base($device).'/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json', $username, $password, [
            'faceLibType' => 'blackFD',
            'FDID' => '1',
            'FPID' => $userId,
            'faceURL' => $url,
        ]);
        if ($this->ok($linked)) {
            return;
        }

        $this->uploadJpeg($device, $userId, $jpeg);
    }

    private function uploadJpeg(Device $device, string $userId, string $jpeg): void
    {
        [$username, $password] = $this->login($device);
        $boundary = 'hrms'.bin2hex(random_bytes(8));
        $record = json_encode([
            'faceLibType' => 'blackFD',
            'FDID' => '1',
            'FPID' => $userId,
        ], JSON_UNESCAPED_UNICODE);
        $body = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"FaceDataRecord\"\r\n"
            ."Content-Type: application/json\r\n\r\n"
            .$record."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"FaceImage\"; filename=\"face.jpg\"\r\n"
            ."Content-Type: image/jpeg\r\n\r\n"
            .$jpeg."\r\n"
            ."--{$boundary}--\r\n";
        $response = $this->client->call(
            'POST',
            $this->base($device).'/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json',
            $username,
            $password,
            $body,
            20,
            'multipart/form-data; boundary='.$boundary,
        );
        if (! $this->ok($response)) {
            throw new RuntimeException($this->problem($response));
        }
    }

    private function saveFinger(Device $device, string $userId, int $fingerNo, string $data): void
    {
        [$username, $password] = $this->login($device);
        $response = $this->client->call('POST', $this->base($device).'/ISAPI/AccessControl/FingerPrint/SetUp?format=json', $username, $password, [
            'FingerPrintCfg' => [
                'employeeNo' => $userId,
                'enableCardReader' => [1],
                'fingerPrintID' => $fingerNo,
                'fingerType' => 'normalFP',
                'fingerData' => $data,
            ],
        ]);
        if (! $this->ok($response)) {
            throw new RuntimeException($this->problem($response));
        }
    }

    /**
     * @return array{UserInfo: array<string, mixed>}
     */
    private function userBody(string $userId, string $name, ?string $password, bool $enabled = true, string $userType = 'normal'): array
    {
        $info = [
            'employeeNo' => $userId,
            'name' => mb_substr($name, 0, 32),
            'userType' => $userType === 'blackList' ? 'blackList' : 'normal',
            'Valid' => [
                'enable' => $enabled,
                'beginTime' => '2020-01-01T00:00:00',
                'endTime' => $enabled ? '2037-12-31T23:59:59' : '2020-01-01T00:00:01',
                'timeType' => 'local',
            ],
            'doorRight' => '1',
            'RightPlan' => [[
                'doorNo' => 1,
                'planTemplateNo' => '1',
            ]],
        ];
        if (is_string($password) && $password !== '') {
            $info['password'] = $password;
        }

        return ['UserInfo' => $info];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function login(Device $device): array
    {
        $password = $device->terminalPassword();
        if ($password === '') {
            throw new RuntimeException('Set the face terminal password in Devices before enrolling.');
        }

        return [$device->terminalUsername(), $password];
    }

    private function base(Device $device): string
    {
        [$host, $port] = $this->endpoint($device);
        if ($host === '') {
            throw new RuntimeException($device->name.' has no IP address.');
        }
        $address = $port === 80 ? $host : $host.':'.$port;

        return 'http://'.$address;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function endpoint(Device $device): array
    {
        return $device->dialAddress();
    }

    private function xml(string $root, string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<'.$root.' version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .$inner
            .'</'.$root.'>';
    }

    private function jpegBytes(?string $stored): ?string
    {
        $bytes = StoredImage::bytes($stored);

        return is_string($bytes) && str_starts_with($bytes, "\xFF\xD8\xFF") ? $bytes : null;
    }

    private function value(string $body, string $tag): string
    {
        $json = json_decode($body, true);
        if (is_array($json)) {
            $found = data_get($json, 'CaptureFaceData.'.$tag)
                ?? data_get($json, 'CaptureFingerPrint.'.$tag)
                ?? data_get($json, $tag);
            if (is_string($found) || is_numeric($found)) {
                $text = trim((string) $found);
                if ($text !== '') {
                    return $text;
                }
            }
        }
        if (preg_match('/<'.$tag.'>([^<]+)</', $body, $match) === 1) {
            return trim($match[1]);
        }

        return '';
    }

    private function ok(array $response): bool
    {
        if ($response['status'] < 200 || $response['status'] >= 300 || $response['body'] === '') {
            return false;
        }
        if (preg_match('/<statusCode>\s*(\d+)/', $response['body'], $match) === 1) {
            return (int) $match[1] === 1;
        }
        $json = json_decode($response['body'], true);
        if (is_array($json) && array_key_exists('statusCode', $json)) {
            return (int) $json['statusCode'] === 1;
        }

        return true;
    }

    private function exists(array $response): bool
    {
        return str_contains($response['body'], 'employeeNoAlreadyExist');
    }

    private function missing(array $response): bool
    {
        return str_contains($response['body'], 'employeeNoNotExist') || str_contains($response['body'], 'notExist');
    }

    private function problem(array $response): string
    {
        $json = json_decode($response['body'], true);
        $detail = is_array($json) ? (string) ($json['errorMsg'] ?? $json['subStatusCode'] ?? $json['statusString'] ?? '') : '';
        if ($detail === '' && preg_match('/<statusString>([^<]+)</', $response['body'], $match) === 1) {
            $detail = $match[1];
        }

        return trim('The face terminal returned HTTP '.$response['status'].' '.$detail);
    }
}
