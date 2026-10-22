<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Support\StoredImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EzapiGateway
{
    public function __construct(
        private readonly DahuaPush $push,
        private readonly DeviceSync $sync,
    ) {}

    /**
     * @return array{status: int, body: string}
     */
    public function handle(string $action, Request $request): array
    {
        if ($action === 'status') {
            return ['status' => 200, 'body' => $this->statusBody()];
        }

        $payload = $this->payload($request);
        $registerId = $this->registerId($request, $payload);
        if ($registerId === '' && $action === 'request') {
            $registerId = $this->remembered($request->ip());
        }
        if ($registerId !== '') {
            $this->seen($registerId, $request->ip(), $action);
        }

        $device = $registerId === '' ? null : Device::query()->where('register_id', $registerId)->first();

        return match ($action) {
            'init' => ['status' => 200, 'body' => $this->initBody($payload)],
            'request' => ['status' => 200, 'body' => $device ? $this->commandFor($device) : ''],
            'result' => $this->result($device, $request),
            'event', 'general' => $this->event($device, $payload, $request),
            default => ['status' => 404, 'body' => 'Not Found'],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function initBody(array $payload): string
    {
        $caps = $payload['DataPushCaps'] ?? [];
        $codes = is_array($caps) ? ($caps['Event']['CodeList'] ?? null) : null;
        $types = is_array($caps) ? ($caps['General']['TypeList'] ?? null) : null;
        $event = ['Enable' => true];
        if (is_array($codes)) {
            $event['CodeList'] = array_values($codes);
        }
        $detail = ['Event' => $event];
        if (is_array($types)) {
            $detail['General'] = ['Enable' => true, 'TypeList' => array_values($types)];
        }

        $json = json_encode([
            'SycAction' => 1,
            'PandingTime' => 10,
            'CmdGetEnable' => true,
            'DataPushEnable' => true,
            'DataPushDetail' => $detail,
            'TimeCheck' => [
                'UTC' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'Tolerance' => 3,
                'TimeZone' => 13,
            ],
        ], JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }

    private function commandFor(Device $device): string
    {
        try {
            $this->push->importInbox($device);
            $this->push->continueCollect($device);
        } catch (\Throwable) {
        }

        $deadline = microtime(true) + $this->pollSeconds();
        do {
            $body = $this->takeCommand($device);
            if ($body !== '') {
                return $body;
            }
            if (microtime(true) >= $deadline || connection_aborted()) {
                break;
            }
            usleep(300000);
        } while (microtime(true) < $deadline);

        return '';
    }

    private function pollSeconds(): int
    {
        return max(0, (int) config('dahua.poll_seconds', 10));
    }

    private function takeCommand(Device $device): string
    {
        $pending = $this->push->directory().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending';
        if (! is_dir($pending)) {
            return '';
        }

        $prefix = $this->safe($this->push->registerId($device)).'__';
        $names = [];
        foreach (File::files($pending) as $file) {
            $name = $file->getFilename();
            if (str_starts_with($name, $prefix) && str_ends_with($name, '.json')) {
                $names[] = $name;
            }
        }
        sort($names);
        if ($names === []) {
            return '';
        }

        $name = $names[0];
        $source = $pending.DIRECTORY_SEPARATOR.$name;
        $raw = File::get($source);
        $decoded = json_decode($raw, true);
        $uri = is_array($decoded) ? (string) data_get($decoded, 'Request.URI', '') : '';
        $method = is_array($decoded) ? (string) data_get($decoded, 'Request.Method', '') : '';
        if ($uri === '' || $method === '') {
            File::ensureDirectoryExists($this->push->directory().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'failed');
            File::move($source, $this->push->directory().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'failed'.DIRECTORY_SEPARATOR.$name);

            return '';
        }

        $sent = $this->push->directory().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sent';
        File::ensureDirectoryExists($sent);
        File::move($source, $sent.DIRECTORY_SEPARATOR.$name);

        return $raw;
    }

    /**
     * @return array{status: int, body: string}
     */
    private function result(?Device $device, Request $request): array
    {
        $raw = $request->getContent();
        if ($device && $raw !== '') {
            $inbox = $this->push->directory().DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox';
            File::ensureDirectoryExists($inbox);
            $name = $this->safe($this->push->registerId($device)).'__'.now()->format('YmdHisu').'.json';
            File::put($inbox.DIRECTORY_SEPARATOR.$name, $raw);
            try {
                $this->push->importInbox($device);
                $this->push->continueCollect($device);
            } catch (\Throwable) {
            }
        }

        return ['status' => 200, 'body' => ''];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: string}
     */
    private function event(?Device $device, array $payload, Request $request): array
    {
        $seq = trim((string) ($payload['DataSeq'] ?? ''));
        if ($device && isset($payload['NotifyInfo']) && is_array($payload['NotifyInfo'])) {
            try {
                $this->sync->receive($device, $payload, $seq !== '' ? $seq : (string) now()->format('YmdHisu'), $this->image($request));
            } catch (\Throwable) {
            }
        }

        $json = json_encode(['DataSeq' => $seq], JSON_UNESCAPED_SLASHES);

        return ['status' => 200, 'body' => $json === false ? '' : $json];
    }

    private function image(Request $request): ?string
    {
        $raw = $request->getContent();
        $start = strpos($raw, "\xFF\xD8\xFF");
        if ($start === false) {
            return null;
        }
        $end = strpos($raw, "\xFF\xD9", $start);
        $bytes = $end === false ? substr($raw, $start) : substr($raw, $start, $end - $start + 2);
        if ($bytes === '') {
            return null;
        }

        return StoredImage::normalize(base64_encode($bytes));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function registerId(Request $request, array $payload): string
    {
        $fromQuery = trim((string) $request->query('RegisterID', ''));
        if ($fromQuery !== '') {
            return $fromQuery;
        }

        return trim((string) ($payload['RegisterID'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $raw = $request->getContent();
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }

        $mark = strpos($raw, '"RegisterID"');
        $start = $mark === false ? strpos($raw, '{') : strrpos(substr($raw, 0, $mark), '{');
        if ($start === false) {
            return [];
        }

        $depth = 0;
        $length = strlen($raw);
        for ($i = $start; $i < $length; $i++) {
            if ($raw[$i] === '{') {
                $depth++;
            } elseif ($raw[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode(substr($raw, $start, $i - $start + 1), true);

                    return is_array($decoded) ? $decoded : [];
                }
            }
        }

        return [];
    }

    private function seen(string $registerId, ?string $ip, string $action): void
    {
        $dir = $this->push->directory().DIRECTORY_SEPARATOR.'runtime';
        File::ensureDirectoryExists($dir);
        $row = [
            'register_id' => $registerId,
            'ip' => (string) $ip,
            'port' => 443,
            'action' => $action,
            'last_seen' => now()->toIso8601String(),
        ];
        File::put($dir.DIRECTORY_SEPARATOR.'latest.json', json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::ensureDirectoryExists($dir.DIRECTORY_SEPARATOR.'ip');
        File::put($dir.DIRECTORY_SEPARATOR.'ip'.DIRECTORY_SEPARATOR.sha1((string) $ip).'.json', json_encode($row));
        $hitsPath = $dir.DIRECTORY_SEPARATOR.'hits.json';
        $hits = is_file($hitsPath) ? json_decode((string) file_get_contents($hitsPath), true) : [];
        $hits = is_array($hits) ? $hits : [];
        $hits[] = $row;
        File::put($hitsPath, json_encode(array_slice($hits, -20)));
    }

    private function remembered(?string $ip): string
    {
        $path = $this->push->directory().DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'ip'.DIRECTORY_SEPARATOR.sha1((string) $ip).'.json';
        $row = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($row)) {
            return '';
        }
        $seen = strtotime((string) ($row['last_seen'] ?? ''));
        if ($seen === false || $seen < time() - 120) {
            return '';
        }

        return trim((string) ($row['register_id'] ?? ''));
    }

    private function statusBody(): string
    {
        $dir = $this->push->directory().DIRECTORY_SEPARATOR.'runtime';
        $latest = is_file($dir.DIRECTORY_SEPARATOR.'latest.json')
            ? json_decode((string) file_get_contents($dir.DIRECTORY_SEPARATOR.'latest.json'), true)
            : null;
        $hits = is_file($dir.DIRECTORY_SEPARATOR.'hits.json')
            ? json_decode((string) file_get_contents($dir.DIRECTORY_SEPARATOR.'hits.json'), true)
            : [];
        $json = json_encode([
            'latest' => is_array($latest) ? $latest : null,
            'hits' => is_array($hits) ? $hits : [],
        ], JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    private function safe(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $value) ?: 'device';
    }
}
