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
            EmployeeCredential::query()
                ->where('employee_id', $employee->id)
                ->where('kind', 'face')
                ->where('slot', '!=', '0')
                ->delete();
            $this->people->storeItem($employee, ['PhotoData' => [['slot' => 0, 'data' => base64_encode($jpeg)]]]);

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
     * @param  list<string|array{id?: int, data?: string}>  $prints
     */
    public function storeProfile(Device $device, string $userId, string $name, ?string $password, ?string $face, array $prints, bool $enabled = true, string $model = ''): void
    {
        // Modify replaces the whole person and drops the face already on the
        // machine. Create only when missing, then write the face on top.
        $this->ensureUser($device, $userId, $name, $password, false, $enabled);
        $jpeg = $this->jpegBytes($face);
        if ($jpeg !== null || $model !== '') {
            $this->uploadJpeg($device, $userId, $jpeg ?? '', $model);
        }
        foreach (array_values($prints) as $index => $print) {
            if (is_array($print)) {
                $fingerNo = (int) ($print['id'] ?? ($index + 1));
                $data = (string) ($print['data'] ?? '');
            } else {
                $fingerNo = $index + 1;
                $data = (string) $print;
            }
            if ($fingerNo < 1 || $fingerNo > 10 || $data === '') {
                continue;
            }
            $this->saveFinger($device, $userId, $fingerNo, $data);
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

    public function replaceFace(Device $device, string $userId, string $jpeg, string $model = ''): void
    {
        $this->uploadJpeg($device, $userId, $jpeg, $model);
    }

    /**
     * Write one existing face onto another terminal without rebuilding it.
     * The model from the terminal that already matches is sent unchanged.
     */
    public function putSameFace(Device $device, string $userId, string $name, string $jpeg, string $model): void
    {
        $name = trim($name) !== '' ? trim($name) : $userId;
        $this->ensureUser($device, $userId, $name, null, false);
        $this->uploadJpeg($device, $userId, $jpeg, $model);
    }

    /**
     * Put a person back to a normal user and write the face after that.
     * Modify clears the face and fingers, so both are sent again in the same step.
     *
     * @param  list<array{id: int, data: string}>  $prints
     */
    public function restoreFace(Device $device, string $userId, string $name, string $jpeg, string $model = '', array $prints = []): void
    {
        $name = trim($name) !== '' ? trim($name) : $userId;
        $this->ensureUser($device, $userId, $name, null, false);
        $this->setUserType($device, $userId, $name, 'normal');
        $this->uploadJpeg($device, $userId, $jpeg, $model);
        foreach ($prints as $print) {
            $fingerNo = (int) ($print['id'] ?? 0);
            $data = trim((string) ($print['data'] ?? ''));
            if ($fingerNo < 1 || $fingerNo > 10 || $data === '') {
                continue;
            }
            try {
                $this->saveFinger($device, $userId, $fingerNo, $data);
            } catch (\Throwable) {
            }
        }
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
     * OUT terminal person type. blackList cannot scan. normal can.
     * SetUp changes only that type. Modify would drop the face and the fingerprint.
     */
    public function setUserType(Device $device, string $userId, string $name, string $userType): void
    {
        $userType = $userType === 'blackList' ? 'blackList' : 'normal';
        [$username, $secret] = $this->login($device);
        $body = $this->userBody($userId, $name, null, true, $userType);
        $base = $this->base($device);
        $updated = $this->client->call('PUT', $base.'/ISAPI/AccessControl/UserInfo/SetUp?format=json', $username, $secret, $body);
        if ($this->ok($updated)) {
            return;
        }
        if (! $this->missing($updated)) {
            throw new RuntimeException($this->problem($updated));
        }
        $created = $this->client->call('POST', $base.'/ISAPI/AccessControl/UserInfo/Record?format=json', $username, $secret, $body);
        if (! $this->ok($created) && ! $this->exists($created)) {
            throw new RuntimeException($this->problem($created));
        }
    }

    private function captureFace(Device $device, string $userId): string
    {
        [$username, $password] = $this->login($device);
        $base = $this->base($device);
        $types = $this->faceDataTypes($device);
        $plans = array_values(array_unique(array_merge(
            in_array('binary', $types, true) ? ['binary'] : [],
            $types,
            ['binary'],
        )));
        $response = ['status' => 0, 'body' => ''];
        $keptByCamera = false;
        foreach ($plans as $type) {
            $keptByCamera = $type === 'binary';
            $inner = '<captureInfrared>false</captureInfrared><dataType>'.$type.'</dataType>';
            if ($type === 'binary') {
                $inner .= '<readerID>1</readerID><employeeNo>'.htmlspecialchars($userId, ENT_XML1).'</employeeNo>';
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
                if (! $keptByCamera) {
                    $this->uploadJpeg($device, $userId, $jpeg);
                }

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
            $saved = $this->waitForSavedFace($userId, $username, $password, $base);
            if ($saved !== null) {
                if (! $keptByCamera) {
                    $this->uploadJpeg($device, $userId, $saved);
                }

                return $saved;
            }
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

        if (! $keptByCamera) {
            $this->bindFace($device, $userId, $url, $jpeg);
        }

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

    private function waitForSavedFace(string $userId, string $username, string $password, string $base): ?string
    {
        $deadline = microtime(true) + 16;
        do {
            $jpeg = $this->savedFace($userId, $username, $password, $base);
            if ($jpeg !== null) {
                return $jpeg;
            }
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(1500000);
        } while (true);

        return null;
    }

    private function savedFace(string $userId, string $username, string $password, string $base): ?string
    {
        try {
            $found = $this->client->call('POST', $base.'/ISAPI/Intelligent/FDLib/FDSearch?format=json', $username, $password, [
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'faceLibType' => 'blackFD',
                'FDID' => '1',
                'FPID' => $userId,
            ], 8);
        } catch (\Throwable) {
            return null;
        }
        $json = json_decode($found['body'], true);
        $url = trim((string) (is_array($json) ? data_get($json, 'MatchList.0.faceURL') : ''));
        if ($url === '') {
            return null;
        }
        try {
            $photo = $this->client->call('GET', $this->publicPhotoUrl($url, $base), $username, $password, null, 12);
        } catch (\Throwable) {
            return null;
        }

        return $photo['status'] === 200 && str_starts_with($photo['body'], "\xFF\xD8\xFF") ? $photo['body'] : null;
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
            $this->learnFace($device, $userId);

            return;
        }

        $this->uploadJpeg($device, $userId, $jpeg);
    }

    private function uploadJpeg(Device $device, string $userId, string $jpeg, string $model = ''): void
    {
        [$username, $password] = $this->login($device);
        $base = $this->base($device);
        $picture = $model === '' ? $this->withinFaceLimit($jpeg) : $jpeg;
        if ($model !== '' && strlen($picture) > 200 * 1024) {
            $picture = $this->withinFaceLimit($picture);
        }
        $response = $this->sendFace($device, $username, $password, 'POST', $base.'/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json', $userId, $picture, $model);
        if (! $this->ok($response) && $this->rejectedFace($response)) {
            if ($model === '') {
                $smaller = $this->shrinkFace($picture !== '' ? $picture : $jpeg, 480, 120 * 1024);
                if (is_string($smaller) && $smaller !== '') {
                    $picture = $smaller;
                }
                $response = $this->sendFace($device, $username, $password, 'POST', $base.'/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json', $userId, $picture, '');
            }
        }
        if (! $this->ok($response) && $this->rejectedFace($response)) {
            $response = $this->sendFace($device, $username, $password, 'PUT', $base.'/ISAPI/Intelligent/FDLib/FDSetUp?format=json', $userId, $picture, $model);
        }
        if (! $this->ok($response)) {
            throw new RuntimeException($this->problem($response));
        }
        if ($model === '') {
            $this->learnFace($device, $userId);
        }
    }

    /**
     * @return array{status: int, body: string}
     */
    private function sendFace(Device $device, string $username, string $password, string $method, string $url, string $userId, string $jpeg, string $model): array
    {
        $record = [
            'faceLibType' => 'blackFD',
            'FDID' => '1',
            'FPID' => $userId,
        ];
        if ($model !== '') {
            $record['modelData'] = $model;
        }
        if (! str_starts_with($jpeg, "\xFF\xD8\xFF")) {
            if ($model === '') {
                throw new RuntimeException($device->name.' has no face picture to restore for '.$userId.'.');
            }

            return $this->client->call($method, $url, $username, $password, $record);
        }

        $boundary = 'hrms'.bin2hex(random_bytes(8));
        $encoded = (string) json_encode($record, JSON_UNESCAPED_UNICODE);
        $body = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"FaceDataRecord\"\r\n"
            ."Content-Type: application/json\r\n"
            .'Content-Length: '.strlen($encoded)."\r\n\r\n"
            .$encoded."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"FaceImage\"; filename=\"face.jpg\"\r\n"
            ."Content-Type: image/jpeg\r\n"
            .'Content-Length: '.strlen($jpeg)."\r\n\r\n"
            .$jpeg."\r\n"
            ."--{$boundary}--\r\n";

        return $this->client->call($method, $url, $username, $password, $body, 20, 'multipart/form-data; boundary='.$boundary);
    }

    /**
     * @param  array{status: int, body: string}  $response
     */
    private function rejectedFace(array $response): bool
    {
        return $response['status'] === 400 || str_contains($response['body'], 'saveFacePic');
    }

    /**
     * The person page shows a picture even when the terminal has no face model.
     * IN can match because it stored the model at capture. OUT then only has the
     * picture and keeps asking to adjust the face. This asks OUT to build the model.
     */
    private function learnFace(Device $device, string $userId): void
    {
        [$username, $password] = $this->login($device);
        $base = $this->base($device);
        $id = rawurlencode($userId);
        foreach (['FPID', 'PID'] as $key) {
            try {
                $started = $this->client->call(
                    'GET',
                    $base.'/ISAPI/Intelligent/FDLib/manualModeling?format=json&FDID=1&'.$key.'='.$id,
                    $username,
                    $password,
                    null,
                    20,
                );
            } catch (\Throwable) {
                continue;
            }
            if ($this->modelingFailed($started['body'])) {
                throw new RuntimeException($device->name.' has the picture for '.$userId.', but it could not learn that face.');
            }
            if ($started['status'] === 200) {
                return;
            }
        }
    }

    private function modelingFailed(string $body): bool
    {
        if (preg_match('/<status>\s*failed\s*<\/status>/i', $body) === 1) {
            return true;
        }
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return false;
        }
        $status = strtolower(trim((string) (
            data_get($json, 'ModelingStatus.status')
            ?? data_get($json, 'modelingStatus')
            ?? ''
        )));

        return $status === 'failed';
    }

    private function withinFaceLimit(string $jpeg): string
    {
        if (! str_starts_with($jpeg, "\xFF\xD8\xFF") || strlen($jpeg) <= 200 * 1024) {
            return $jpeg;
        }

        return $this->shrinkFace($jpeg, 640, 180 * 1024) ?? $jpeg;
    }

    private function shrinkFace(string $jpeg, int $maxEdge, int $limit): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg') || ! str_starts_with($jpeg, "\xFF\xD8\xFF")) {
            return null;
        }
        $image = @imagecreatefromstring($jpeg);
        if ($image === false) {
            return null;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width > $maxEdge || $height > $maxEdge) {
            $scale = $maxEdge / max($width, $height);
            $scaled = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagecopyresampled($scaled, $image, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), $width, $height);
            imagedestroy($image);
            $image = $scaled;
        }
        $fitted = null;
        foreach ([75, 60, 45, 30] as $quality) {
            ob_start();
            imagejpeg($image, null, $quality);
            $fitted = (string) ob_get_clean();
            if ($fitted !== '' && str_starts_with($fitted, "\xFF\xD8\xFF") && strlen($fitted) <= $limit) {
                imagedestroy($image);

                return $fitted;
            }
        }
        imagedestroy($image);

        return is_string($fitted) && str_starts_with($fitted, "\xFF\xD8\xFF") && strlen($fitted) <= $limit ? $fitted : null;
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
        if (preg_match('/<'.$tag.'\b[^>]*>\s*(?:<!\[CDATA\[)?\s*([^<\]]+)/i', $body, $match) === 1) {
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
