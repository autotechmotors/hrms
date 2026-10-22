<?php

namespace App\Services\Devices;

use App\Models\Device;
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

    public function flush(): void
    {
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
                continue;
            }
            if ($this->post($payload)) {
                File::delete($file->getPathname());
            }
        }
    }

    /**
     * @param  array{register_id: string, seq: string, image: ?string, event: array<string, mixed>}  $payload
     */
    private function post(array $payload): bool
    {
        $url = (string) config('dahua.forward_url');
        $token = (string) config('dahua.forward_token');
        if ($url === '' || $token === '') {
            return false;
        }
        if ($this->sameServer($url)) {
            return true;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Device-Token' => $token,
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (\Throwable) {
            $this->keep($payload);

            return false;
        }

        if ($response->successful() && ! $response->json('pending')) {
            return true;
        }

        if ($response->status() === 422) {
            return true;
        }

        $this->keep($payload);

        return false;
    }

    private function sameServer(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $app = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host !== '' && $host === $app;
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
        return storage_path('app/device-forward');
    }
}
