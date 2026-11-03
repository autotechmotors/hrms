<?php

namespace App\Services\Devices;

use App\Models\Device;
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

        $this->ensureUser($device, $userId, $name, $password);
        if ($kind === 'password') {
            return;
        }
        if ($kind === 'face') {
            $this->people->storeItem($employee, ['PhotoData' => [base64_encode($this->takenFace($device, $userId, $name))]]);

            return;
        }
        if ($kind === 'finger') {
            $fingerNo = EmployeeCredential::query()
                ->where('employee_id', $employee->id)
                ->where('kind', 'finger')
                ->count() + 1;
            $data = $this->takenFinger($device, $userId, $name, $fingerNo);
            $this->people->storeItem($employee, ['FingerPrints' => [['FingerprintID' => $fingerNo, 'FingerprintData' => $data]]]);

            return;
        }

        throw new RuntimeException($device->name.' adds a face or a finger. Stand at that terminal and use those buttons.');
    }

    /**
     * @param  list<string>  $prints
     */
    public function storeProfile(Device $device, string $userId, string $name, ?string $password, ?string $face, array $prints): void
    {
        $this->ensureUser($device, $userId, $name, $password);
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
        [$username, $password] = $this->login();
        $response = $this->client->call('PUT', $this->base($device).'/ISAPI/AccessControl/UserInfo/Delete?format=json', $username, $password, [
            'UserInfoDelCond' => ['EmployeeNoList' => [['employeeNo' => $userId]]],
        ]);
        if (! $this->ok($response) && ! $this->missing($response)) {
            throw new RuntimeException($this->problem($response));
        }
    }

    public function reachable(Device $device): bool
    {
        if ((string) config('hikvision.password') === '') {
            return false;
        }
        if (config('hikvision.direct') === false) {
            return false;
        }
        if (app()->runningUnitTests()) {
            return true;
        }

        [$host, $port] = $this->endpoint($device);
        if ($host === '') {
            return false;
        }
        $socket = @fsockopen($host, $port, $errno, $error, 3);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    public function takenFace(Device $device, string $userId, string $name): string
    {
        $this->ensureUser($device, $userId, $name);

        return $this->captureFace($device, $userId);
    }

    public function takenFinger(Device $device, string $userId, string $name, int $fingerNo): string
    {
        if ($fingerNo < 1 || $fingerNo > 10) {
            throw new RuntimeException($device->name.' already has 10 fingers for this person.');
        }
        $this->ensureUser($device, $userId, $name);

        return $this->captureFinger($device, $userId, $fingerNo);
    }

    public function ensureUser(Device $device, string $userId, string $name, ?string $password = null): void
    {
        [$username, $secret] = $this->login();
        $body = $this->userBody($userId, $name, $password);
        $base = $this->base($device);
        $created = $this->client->call('POST', $base.'/ISAPI/AccessControl/UserInfo/Record?format=json', $username, $secret, $body);
        if ($this->ok($created)) {
            return;
        }
        if (! $this->exists($created)) {
            throw new RuntimeException($this->problem($created));
        }

        $updated = $this->client->call('PUT', $base.'/ISAPI/AccessControl/UserInfo/Modify?format=json', $username, $secret, $body);
        if (! $this->ok($updated)) {
            throw new RuntimeException($this->problem($updated));
        }
    }

    private function captureFace(Device $device, string $userId): string
    {
        [$username, $password] = $this->login();
        $base = $this->base($device);
        $response = $this->client->call('POST', $base.'/ISAPI/AccessControl/CaptureFaceData', $username, $password, $this->xml('CaptureFaceDataCond', '<captureInfrared>false</captureInfrared><dataType>url</dataType>'), 55);
        if (! $this->ok($response)) {
            throw new RuntimeException($this->problem($response));
        }

        $url = $this->value($response['body'], 'faceDataUrl');
        if ($url === '') {
            throw new RuntimeException('Look at '.$device->name.' until the face frame finishes, then try again.');
        }
        if (str_starts_with($url, '/')) {
            $url = $base.$url;
        }

        $photo = $this->client->call('GET', $url, $username, $password, null, 15);
        $jpeg = $photo['status'] === 200 ? $photo['body'] : '';
        if ($jpeg === '' || ! str_starts_with($jpeg, "\xFF\xD8\xFF")) {
            throw new RuntimeException('The face terminal took a picture, but HRMS could not read it.');
        }

        $this->bindFace($device, $userId, $url, $jpeg);

        return $jpeg;
    }

    private function captureFinger(Device $device, string $userId, int $fingerNo): string
    {
        [$username, $password] = $this->login();
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
        [$username, $password] = $this->login();
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
        [$username, $password] = $this->login();
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
        [$username, $password] = $this->login();
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
    private function userBody(string $userId, string $name, ?string $password): array
    {
        $info = [
            'employeeNo' => $userId,
            'name' => mb_substr($name, 0, 32),
            'userType' => 'normal',
            'Valid' => [
                'enable' => true,
                'beginTime' => '2020-01-01T00:00:00',
                'endTime' => '2037-12-31T23:59:59',
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
    private function login(): array
    {
        $password = trim((string) config('hikvision.password'));
        if ($password === '') {
            throw new RuntimeException('Set HIKVISION_PASSWORD before enrolling on the face terminal.');
        }

        return [(string) config('hikvision.username'), $password];
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
        $configured = trim((string) config('hikvision.host'));
        $port = config('hikvision.port');
        if ($configured !== '' && is_int($port) && $port > 0) {
            $configured = (string) preg_replace('#^https?://#', '', $configured);
            $configured = explode('/', $configured)[0];
            if (str_contains($configured, ':')) {
                [$configured, $written] = explode(':', $configured, 2);
                $port = (int) $written;
            }

            return [$configured, $port];
        }

        $host = trim((string) $device->ip_address);
        if ($host === '') {
            $host = $configured;
        }
        $host = (string) preg_replace('#^https?://#', '', $host);
        $host = explode('/', $host)[0];
        $devicePort = 80;
        if (str_contains($host, ':')) {
            [$host, $written] = explode(':', $host, 2);
            $devicePort = (int) $written;
        }

        return [$host, $devicePort > 0 ? $devicePort : 80];
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
            if (is_string($found) && trim($found) !== '') {
                return trim($found);
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
