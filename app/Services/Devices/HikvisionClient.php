<?php

namespace App\Services\Devices;

use RuntimeException;

class HikvisionClient
{
    public function __construct(private mixed $request = null) {}

    /**
     * @param  array<string, mixed>|string|null  $body
     * @return array{status: int, body: string}
     */
    public function call(string $method, string $url, string $username, string $password, array|string|null $body = null, int $timeout = 12, ?string $contentType = null): array
    {
        if (is_callable($this->request)) {
            $response = ($this->request)($method, $url, $body);

            return [
                'status' => (int) ($response['status'] ?? 0),
                'body' => (string) ($response['body'] ?? ''),
            ];
        }

        if (! function_exists('curl_init')) {
            throw new RuntimeException('PHP curl is required to reach the Hikvision device.');
        }

        $headers = [is_string($body) ? 'Accept: */*' : 'Accept: application/json'];
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $username.':'.$password,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(3, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if (is_string($body)) {
            $headers[] = 'Content-Type: '.($contentType ?: 'application/xml');
            $options[CURLOPT_POSTFIELDS] = $body;
        } elseif ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new RuntimeException('Could not encode the Hikvision request.');
            }
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = $json;
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Hikvision connection failed: '.$error);
        }

        return ['status' => $status, 'body' => (string) $raw];
    }
}
