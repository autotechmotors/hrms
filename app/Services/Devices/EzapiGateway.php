<?php

namespace App\Services\Devices;

use App\Models\Company;
use App\Models\Device;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class EzapiGateway
{
    public function __construct(
        private readonly DahuaPush $push,
        private readonly DeviceSync $sync,
    ) {}

    public function handle(Request $request, string $action): Response
    {
        $body = $this->decode($request->getContent());
        $registerId = $this->registerId($request, $body);
        if ($registerId !== '') {
            $this->seen($registerId, (string) $request->ip());
        }

        return match ($action) {
            'init' => $this->init($body),
            'request' => $this->command($registerId),
            'result' => $this->result($registerId, $request->getContent(), $body),
            'event', 'general' => $this->event($registerId, $request->getContent(), $body),
            default => response('Not Found', 404),
        };
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function init(?array $body): Response
    {
        $event = ['Enable' => true, 'CodeList' => null];
        $codes = data_get($body, 'DataPushCaps.Event.CodeList');
        if (is_array($codes)) {
            $event['CodeList'] = array_values($codes);
        }

        $detail = ['Event' => $event];
        if (is_array(data_get($body, 'DataPushCaps.General'))) {
            $general = ['Enable' => true, 'TypeList' => null];
            $types = data_get($body, 'DataPushCaps.General.TypeList');
            if (is_array($types)) {
                $general['TypeList'] = array_values($types);
            }
            $detail['General'] = $general;
        }

        $json = json_encode([
            'SycAction' => 1,
            'PandingTime' => 10,
            'TimeCheck' => [
                'UTC' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'Tolerance' => 3,
                'TimeZone' => 13,
            ],
            'CmdGetEnable' => true,
            'DataPushEnable' => true,
            'DataPushDetail' => $detail,
        ]);

        return $this->json($json === false ? '{}' : $json);
    }

    private function command(string $registerId): Response
    {
        if ($registerId === '') {
            $registerId = $this->onlyRegisterId();
        }
        $claimed = $registerId === '' ? null : $this->claim($registerId);
        if ($claimed === null) {
            return $this->json('');
        }

        $sent = $this->root().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sent';
        File::ensureDirectoryExists($sent);
        $target = $sent.DIRECTORY_SEPARATOR.basename($claimed);
        if (is_file($target)) {
            File::delete($target);
        }
        File::move($claimed, $target);

        return $this->json((string) File::get($target));
    }

    private function result(string $registerId, string $raw, ?array $body): Response
    {
        if ($body === null) {
            return $this->json('Error', 500);
        }

        $root = $this->root();
        $runtime = $root.DIRECTORY_SEPARATOR.'runtime';
        File::ensureDirectoryExists($runtime);
        File::put($runtime.DIRECTORY_SEPARATOR.'last_result.json', $raw);
        $inbox = $runtime.DIRECTORY_SEPARATOR.'inbox';
        File::ensureDirectoryExists($inbox);
        $name = $this->safe($registerId !== '' ? $registerId : 'device').'__'.now()->format('YmdHisv').'.json';
        File::put($inbox.DIRECTORY_SEPARATOR.$name, $raw);

        $device = $this->device($registerId);
        if ($device) {
            $this->push->importInbox($device);
        }

        return $this->json('');
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function event(string $registerId, string $raw, ?array $body): Response
    {
        if ($body === null) {
            return $this->json('Error', 500);
        }

        $seq = (string) ($body['DataSeq'] ?? '');
        $safe = $this->safe($seq !== '' ? $seq : now()->format('YmdHisv'));
        if ($seq !== '') {
            $dir = $this->root().DIRECTORY_SEPARATOR.'saved_files';
            File::ensureDirectoryExists($dir);
            File::put($dir.DIRECTORY_SEPARATOR.$safe.'.json', $raw);
            $this->savePicture($dir, $safe, $raw);
            $device = $this->device($registerId !== '' ? $registerId : (string) ($body['RegisterID'] ?? ''));
            if ($device) {
                $this->sync->importSaved($device);
            }
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $seq);

        return $this->json('{"DataSeq": "'.$escaped.'"}');
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function registerId(Request $request, ?array $body): string
    {
        $query = trim((string) $request->query('RegisterID', ''));
        if ($query !== '') {
            return $query;
        }

        return trim((string) ($body['RegisterID'] ?? ''));
    }

    private function onlyRegisterId(): string
    {
        $ids = Device::query()->whereNotNull('register_id')->pluck('register_id')->unique()->values();

        return $ids->count() === 1 ? (string) $ids->first() : '';
    }

    private function device(string $registerId): ?Device
    {
        if ($registerId === '') {
            return null;
        }

        $device = Device::query()->where('register_id', $registerId)->first();
        if ($device) {
            return $device;
        }

        $company = Company::query()->orderBy('id')->first();
        if (! $company) {
            return null;
        }

        return AuditLogger::withoutAuditing(fn () => Device::query()->create([
            'company_id' => $company->id,
            'name' => 'Face terminal',
            'register_id' => $registerId,
            'status' => 'online',
            'last_checked_at' => now(),
        ]));
    }

    private function seen(string $registerId, string $ip): void
    {
        $root = $this->root();
        $runtime = $root.DIRECTORY_SEPARATOR.'runtime';
        File::ensureDirectoryExists($runtime);
        $payload = json_encode([
            'register_id' => $registerId,
            'ip' => $ip,
            'port' => 443,
            'last_seen' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload !== false) {
            File::put($runtime.DIRECTORY_SEPARATOR.'latest.json', $payload);
        }

        $device = Device::query()->where('register_id', $registerId)->first();
        if (! $device) {
            return;
        }
        if ($device->last_checked_at && $device->last_checked_at->greaterThan(now()->subSeconds(20))) {
            return;
        }

        AuditLogger::withoutAuditing(function () use ($device, $ip): void {
            $device->forceFill([
                'status' => 'online',
                'ip_address' => $ip !== '' ? $ip : $device->ip_address,
                'last_checked_at' => now(),
            ])->save();
        });
    }

    private function claim(string $registerId): ?string
    {
        $pending = $this->root().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending';
        if (! is_dir($pending)) {
            return null;
        }

        $lockPath = $this->root().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'.claim.lock';
        File::ensureDirectoryExists(dirname($lockPath));
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            return null;
        }
        flock($lock, LOCK_EX);

        try {
            $prefix = $this->safe($registerId).'__';
            $names = [];
            foreach (File::files($pending) as $file) {
                $name = $file->getFilename();
                if (str_starts_with($name, $prefix) && str_ends_with($name, '.json')) {
                    $names[] = $name;
                }
            }
            sort($names);
            if ($names === []) {
                return null;
            }

            $source = $pending.DIRECTORY_SEPARATOR.$names[0];
            $data = json_decode((string) File::get($source), true);
            $uri = is_array($data) ? (string) data_get($data, 'Request.URI', '') : '';
            $method = is_array($data) ? (string) data_get($data, 'Request.Method', '') : '';
            if ($uri === '' || $method === '') {
                $failed = $this->root().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'failed';
                File::ensureDirectoryExists($failed);
                File::move($source, $failed.DIRECTORY_SEPARATOR.$names[0]);

                return null;
            }

            $sending = $this->root().DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sending';
            File::ensureDirectoryExists($sending);
            $claimed = $sending.DIRECTORY_SEPARATOR.$names[0];
            if (is_file($claimed)) {
                File::delete($claimed);
            }
            File::move($source, $claimed);

            return $claimed;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function savePicture(string $dir, string $seq, string $raw): void
    {
        $start = strpos($raw, "\xFF\xD8\xFF");
        if ($start === false) {
            return;
        }
        $jpg = substr($raw, $start);
        $end = strpos($jpg, "\xFF\xD9");
        if ($end === false) {
            return;
        }
        File::put($dir.DIRECTORY_SEPARATOR.$seq.'_Pic.jpg', substr($jpg, 0, $end + 2));
    }

    private function decode(string $raw): ?array
    {
        $raw = ltrim($raw, "\0");
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function root(): string
    {
        return $this->push->directory();
    }

    private function safe(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $value);

        return $safe !== null && $safe !== '' ? $safe : 'device';
    }

    private function json(string $body, int $status = 200): Response
    {
        return response($body, $status, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
