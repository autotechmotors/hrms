<?php

namespace App\Http\Middleware;

use App\Services\Devices\DahuaLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogDahuaApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $call = $request->method().' /'.$request->path();

        DahuaLog::debug('api request '.$call, [
            'ip' => (string) $request->ip(),
            'query' => $request->query(),
            'headers' => $this->headers($request),
            'body' => $this->requestBody($request),
        ]);

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            DahuaLog::debug('api error '.$call, [
                'error' => $exception->getMessage(),
                'ms' => $this->elapsed($started),
            ]);

            throw $exception;
        }

        DahuaLog::debug('api response '.$call, [
            'http' => $response->getStatusCode(),
            'ms' => $this->elapsed($started),
            'body' => $this->responseBody($response),
        ]);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function headers(Request $request): array
    {
        $hidden = ['authorization', 'cookie', 'x-device-token'];
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = in_array(strtolower($name), $hidden, true) ? '[hidden]' : $values;
        }

        return $headers;
    }

    private function requestBody(Request $request): mixed
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return null;
        }

        $imageAt = strpos($raw, "\xFF\xD8\xFF");
        $json = $imageAt === false ? $raw : substr($raw, 0, $imageAt);
        $decoded = json_decode(trim($json), true);
        if (! is_array($decoded)) {
            return substr($raw, 0, 2000);
        }
        if ($imageAt !== false) {
            $decoded['_image_bytes'] = strlen($raw) - $imageAt;
        }

        return $decoded;
    }

    private function responseBody(Response $response): mixed
    {
        $raw = $response->getContent();
        if ($raw === '' || $raw === false) {
            return null;
        }
        if (strlen($raw) > 20000) {
            return substr($raw, 0, 2000).' ... ['.strlen($raw).' bytes]';
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $raw;
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
