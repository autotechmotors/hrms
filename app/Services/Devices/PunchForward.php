<?php

namespace App\Services\Devices;

use App\Models\Device;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class PunchForward
{
    public function send(Device $device, array $event, string $seq, ?string $image): void
    {
        $this->post([
            'register_id' => (string) $device->register_id,
            'seq' => $seq,
            'image' => $image,
            'event' => $event,
        ]);
    }

    /**
     * @return list<array{register_id: string, seq: string, image: ?string, event: array<string, mixed>}>
     */
    public function heldPayloads(): array
    {
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return [];
        }

        $rows = [];
        foreach (File::files($dir) as $file) {
            if (! str_ends_with(strtolower($file->getFilename()), '.json')) {
                continue;
            }
            $payload = json_decode(File::get($file->getPathname()), true);
            if (is_array($payload)) {
                $rows[] = $payload;
            }
        }

        return $rows;
    }

    public function flush(): void
    {
        if (! app()->runningInConsole() || Cache::get('dahua.forward.hold')) {
            return;
        }

        $dir = $this->dir();
        if (! is_dir($dir)) {
            return;
        }
        foreach (File::files($dir) as $file) {
            if (! str_ends_with(strtolower($file->getFilename()), '.json')) {
                continue;
            }
            $payload = json_decode(File::get($file->getPathname()), true);
            if (! is_array($payload)) {
                File::delete($file->getPathname());

                return;
            }
            if (in_array($this->post($payload), ['sent', 'dropped'], true)) {
                File::delete($file->getPathname());
            }

            return;
        }
    }

    /**
     * @return 'sent'|'queued'|'dropped'|'skipped'|'failed'
     */
    public function outcome(Device $device, array $event, string $seq, ?string $image): string
    {
        return $this->post([
            'register_id' => (string) $device->register_id,
            'seq' => $seq,
            'image' => $image,
            'event' => $event,
        ]);
    }

    /**
     * @param  array{register_id: string, seq: string, image: ?string, event: array<string, mixed>}  $payload
     * @return 'sent'|'queued'|'dropped'|'skipped'|'failed'
     */
    private function post(array $payload): string
    {
        $url = (string) config('dahua.forward_url');
        $token = (string) config('dahua.forward_token');
        if ($url === '' || $token === '') {
            return 'skipped';
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'X-Device-Token' => $token,
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (\Throwable) {
            $this->keep($payload);
            Cache::put('dahua.forward.hold', 1, now()->addMinutes(5));

            return 'failed';
        }

        if ($response->successful() && ! $response->json('pending')) {
            return 'sent';
        }

        if ($response->status() === 422) {
            return 'dropped';
        }

        $this->keep($payload);
        $wait = $response->successful() && $response->json('pending') ? 60 : 5;
        Cache::put('dahua.forward.hold', 1, now()->addMinutes($wait));

        return 'queued';
    }

    /**
     * @param  array{register_id: string, seq: string, image: ?string, event: array<string, mixed>}  $payload
     */
    private function keep(array $payload): void
    {
        $dir = $this->dir();
        File::ensureDirectoryExists($dir);
        $name = hash('sha256', json_encode($payload));
        File::put($dir.DIRECTORY_SEPARATOR.$name.'.json', json_encode($payload));
    }

    private function dir(): string
    {
        return (string) config('dahua.forward_dir');
    }
}
