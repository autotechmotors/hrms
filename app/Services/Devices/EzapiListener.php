<?php

namespace App\Services\Devices;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * HTTPS AutoRegister / Push listener for Dahua terminals.
 * Replaces AutoRegisterAndPush_Person.exe — same /ezapi/push/* protocol.
 */
class EzapiListener
{
    /** @var resource|null */
    private $server = null;

    /** @var array<int, array{socket: resource, buffer: string, peer: string, ssl: bool}> */
    private array $clients = [];

    /** @var array<int, array{socket: resource, register_id: string, peer: string, until: float}> */
    private array $waiters = [];

    private bool $running = false;

    public function __construct(private readonly EzapiGateway $gateway) {}

    public function ensureLayout(): void
    {
        $root = rtrim((string) config('dahua.push_dir'), '\\/');
        foreach ([
            $root,
            $root.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'pending',
            $root.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sending',
            $root.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'sent',
            $root.DIRECTORY_SEPARATOR.'commands'.DIRECTORY_SEPARATOR.'failed',
            $root.DIRECTORY_SEPARATOR.'runtime',
            $root.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'inbox',
            $root.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'collects',
            $root.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'ip',
        ] as $dir) {
            File::ensureDirectoryExists($dir);
        }

        $certDir = rtrim((string) config('dahua.cert_dir'), '\\/');
        File::ensureDirectoryExists($certDir);
        $cert = $certDir.DIRECTORY_SEPARATOR.'cert.pem';
        $key = $certDir.DIRECTORY_SEPARATOR.'key.pem';
        if (! is_file($cert) || ! is_file($key)) {
            $seed = resource_path('dahua'.DIRECTORY_SEPARATOR.'certs');
            if (is_file($seed.DIRECTORY_SEPARATOR.'cert.pem') && is_file($seed.DIRECTORY_SEPARATOR.'key.pem')) {
                File::copy($seed.DIRECTORY_SEPARATOR.'cert.pem', $cert);
                File::copy($seed.DIRECTORY_SEPARATOR.'key.pem', $key);
            }
        }
        if (! is_file($cert) || ! is_file($key)) {
            throw new RuntimeException('Missing TLS certs at '.$certDir.' (need cert.pem and key.pem).');
        }
    }

    public function start(int $port, callable $onInfo): void
    {
        $this->ensureLayout();
        $certDir = rtrim((string) config('dahua.cert_dir'), '\\/');

        $context = stream_context_create([
            'ssl' => [
                'local_cert' => $certDir.DIRECTORY_SEPARATOR.'cert.pem',
                'local_pk' => $certDir.DIRECTORY_SEPARATOR.'key.pem',
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER,
            ],
        ]);

        $address = 'tcp://0.0.0.0:'.$port;
        $server = @stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($server === false) {
            throw new RuntimeException("Cannot bind {$address}: {$errstr} ({$errno}). Close AutoRegisterAndPush_Person.exe if it is still running, or run once as Administrator to open the firewall for port {$port}.");
        }

        stream_set_blocking($server, false);
        $this->server = $server;
        $this->running = true;

        $onInfo('EZAPI HTTPS listener on 0.0.0.0:'.$port);
        $onInfo('Push dir: '.config('dahua.push_dir'));
        $onInfo('Point the terminal AutoRegister server to '.config('dahua.host').':'.$port);
        DahuaLog::debug('listener started', [
            'port' => $port,
            'host' => config('dahua.host'),
            'push_dir' => config('dahua.push_dir'),
        ]);

        while ($this->running) {
            $this->tick($onInfo);
        }
    }

    public function stop(): void
    {
        $this->running = false;
        foreach ($this->clients as $client) {
            @fclose($client['socket']);
        }
        foreach ($this->waiters as $waiter) {
            @fclose($waiter['socket']);
        }
        $this->clients = [];
        $this->waiters = [];
        if (is_resource($this->server)) {
            @fclose($this->server);
        }
        $this->server = null;
        DahuaLog::debug('listener stopped');
    }

    private function tick(callable $onInfo): void
    {
        $read = [];
        if (is_resource($this->server)) {
            $read[] = $this->server;
        }
        foreach ($this->clients as $id => $client) {
            if (is_resource($client['socket'])) {
                $read[] = $client['socket'];
            } else {
                unset($this->clients[$id]);
            }
        }

        $write = null;
        $except = null;
        if ($read === []) {
            usleep(100000);

            return;
        }

        $changed = @stream_select($read, $write, $except, 0, 200000);
        if ($changed === false) {
            usleep(50000);

            return;
        }

        foreach ($read as $socket) {
            if ($socket === $this->server) {
                $this->accept($onInfo);
                continue;
            }
            $this->readClient($socket, $onInfo);
        }

        $this->serveWaiters();
    }

    private function accept(callable $onInfo): void
    {
        $peer = '';
        $client = @stream_socket_accept($this->server, 0, $peer);
        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);
        $ok = @stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER);
        if ($ok === false) {
            // Handshake may need more data; keep client and retry on readable.
            $this->clients[(int) $client] = [
                'socket' => $client,
                'buffer' => '',
                'peer' => $peer,
                'ssl' => false,
            ];

            return;
        }
        if ($ok === 0) {
            $this->clients[(int) $client] = [
                'socket' => $client,
                'buffer' => '',
                'peer' => $peer,
                'ssl' => false,
            ];

            return;
        }

        $this->clients[(int) $client] = [
            'socket' => $client,
            'buffer' => '',
            'peer' => $peer,
            'ssl' => true,
        ];
        $onInfo('TLS connected '.$peer);
        DahuaLog::debug('listener tls connected', ['peer' => $peer]);
    }

    /**
     * @param  resource  $socket
     */
    private function readClient($socket, callable $onInfo): void
    {
        $id = (int) $socket;
        if (! isset($this->clients[$id])) {
            return;
        }

        $client = &$this->clients[$id];
        if (! $client['ssl']) {
            $ok = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER);
            if ($ok === false) {
                $onInfo('TLS handshake failed '.$client['peer']);
                DahuaLog::debug('listener tls failed', ['peer' => $client['peer']]);
                $this->dropClient($id);

                return;
            }
            if ($ok === 0) {
                return;
            }
            $client['ssl'] = true;
            $onInfo('TLS connected '.$client['peer']);
            DahuaLog::debug('listener tls connected', ['peer' => $client['peer']]);
        }

        $chunk = @fread($socket, 65536);
        if ($chunk === false || $chunk === '') {
            if (feof($socket)) {
                $this->dropClient($id);
            }

            return;
        }

        $client['buffer'] .= $chunk;
        $parsed = $this->parseHttp($client['buffer']);
        if ($parsed === null) {
            if (strlen($client['buffer']) > 12 * 1024 * 1024) {
                $this->respond($socket, 413, 'Payload Too Large');
                $this->dropClient($id);
            }

            return;
        }

        $client['buffer'] = $parsed['rest'];
        $this->dispatch($socket, $client['peer'], $parsed, $onInfo);
    }

    /**
     * @return array{method: string, path: string, query: string, headers: array<string, string>, body: string, rest: string}|null
     */
    private function parseHttp(string $raw): ?array
    {
        $headerEnd = strpos($raw, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }

        $headerBlob = substr($raw, 0, $headerEnd);
        $bodyStart = $headerEnd + 4;
        $lines = explode("\r\n", $headerBlob);
        $requestLine = array_shift($lines) ?? '';
        $parts = explode(' ', $requestLine);
        if (count($parts) < 2) {
            return null;
        }

        $method = strtoupper($parts[0]);
        $target = $parts[1];
        $path = parse_url($target, PHP_URL_PATH) ?: $target;
        $query = (string) (parse_url($target, PHP_URL_QUERY) ?: '');

        $headers = [];
        foreach ($lines as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
        }

        $length = (int) ($headers['content-length'] ?? 0);
        if ($length < 0 || $length > 10 * 1024 * 1024) {
            return null;
        }
        if (strlen($raw) < $bodyStart + $length) {
            return null;
        }

        $body = substr($raw, $bodyStart, $length);
        $rest = substr($raw, $bodyStart + $length);

        return [
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'headers' => $headers,
            'body' => $body,
            'rest' => $rest === false ? '' : $rest,
        ];
    }

    /**
     * @param  resource  $socket
     * @param  array{method: string, path: string, query: string, headers: array<string, string>, body: string, rest: string}  $parsed
     */
    private function dispatch($socket, string $peer, array $parsed, callable $onInfo): void
    {
        $id = (int) $socket;
        $path = $parsed['path'];
        if (! preg_match('#^/ezapi/push/(init|request|result|event|general|status)$#', $path, $m)) {
            $this->respond($socket, 404, 'Not Found');
            unset($this->clients[$id]);

            return;
        }

        $action = $m[1];
        $ip = $this->peerIp($peer);

        parse_str($parsed['query'], $query);
        $server = [
            'REQUEST_METHOD' => $parsed['method'],
            'REMOTE_ADDR' => $ip,
            'CONTENT_TYPE' => $parsed['headers']['content-type'] ?? 'application/json',
            'HTTP_CONTENT_TYPE' => $parsed['headers']['content-type'] ?? 'application/json',
        ];

        $request = Request::create(
            $path.($parsed['query'] !== '' ? '?'.$parsed['query'] : ''),
            $parsed['method'],
            $query,
            [],
            [],
            $server,
            $parsed['body'],
        );

        if ($action === 'request') {
            $previous = config('dahua.poll_seconds');
            config(['dahua.poll_seconds' => 0]);
            try {
                $result = $this->gateway->handle($action, $request);
            } catch (Throwable $e) {
                config(['dahua.poll_seconds' => $previous]);
                DahuaLog::debug('listener request error', ['error' => $e->getMessage(), 'peer' => $peer]);
                $this->respond($socket, 500, 'Internal Server Error');
                $this->dropClient($id);

                return;
            }
            config(['dahua.poll_seconds' => $previous]);

            if ($result['body'] !== '') {
                $this->respond($socket, $result['status'], $result['body']);
                unset($this->clients[$id]);
                $onInfo('cmd → '.$ip);

                return;
            }

            $registerId = trim((string) ($query['RegisterID'] ?? ''));
            $until = microtime(true) + max(1, (int) config('dahua.poll_seconds', 10));
            $this->waiters[$id] = [
                'socket' => $socket,
                'register_id' => $registerId,
                'peer' => $peer,
                'until' => $until,
            ];
            unset($this->clients[$id]);

            return;
        }

        try {
            $result = $this->gateway->handle($action, $request);
        } catch (Throwable $e) {
            DahuaLog::debug('listener dispatch error', [
                'action' => $action,
                'error' => $e->getMessage(),
                'peer' => $peer,
            ]);
            $this->respond($socket, 500, 'Internal Server Error');
            $this->dropClient($id);

            return;
        }

        $this->respond($socket, $result['status'], $result['body']);
        // Keep connection for HTTP keep-alive style device sessions.
        if (isset($this->clients[$id])) {
            $this->clients[$id]['buffer'] = $parsed['rest'];
        } else {
            $this->clients[$id] = [
                'socket' => $socket,
                'buffer' => $parsed['rest'],
                'peer' => $peer,
                'ssl' => true,
            ];
        }

        if ($action === 'event' || $action === 'general') {
            $onInfo($action.' ← '.$ip);
        }
    }

    private function serveWaiters(): void
    {
        $now = microtime(true);
        foreach ($this->waiters as $id => $waiter) {
            $socket = $waiter['socket'];
            if (! is_resource($socket)) {
                unset($this->waiters[$id]);
                continue;
            }

            $registerId = $waiter['register_id'];
            $body = '';
            if ($registerId !== '') {
                $request = Request::create('/ezapi/push/request?RegisterID='.urlencode($registerId), 'GET', [
                    'RegisterID' => $registerId,
                ], [], [], ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => $this->peerIp($waiter['peer'])]);
                $previous = config('dahua.poll_seconds');
                config(['dahua.poll_seconds' => 0]);
                try {
                    $result = $this->gateway->handle('request', $request);
                    $body = $result['body'];
                } catch (Throwable) {
                    $body = '';
                }
                config(['dahua.poll_seconds' => $previous]);
            }

            if ($body !== '') {
                $this->respond($socket, 200, $body);
                unset($this->waiters[$id]);
                $this->clients[$id] = [
                    'socket' => $socket,
                    'buffer' => '',
                    'peer' => $waiter['peer'],
                    'ssl' => true,
                ];
                continue;
            }

            if ($now >= $waiter['until']) {
                $this->respond($socket, 200, '');
                unset($this->waiters[$id]);
                $this->clients[$id] = [
                    'socket' => $socket,
                    'buffer' => '',
                    'peer' => $waiter['peer'],
                    'ssl' => true,
                ];
            }
        }
    }

    /**
     * @param  resource  $socket
     */
    private function respond($socket, int $status, string $body): void
    {
        $reason = match ($status) {
            200 => 'OK',
            404 => 'Not Found',
            413 => 'Payload Too Large',
            500 => 'Internal Server Error',
            default => 'OK',
        };
        $headers = [
            'HTTP/1.1 '.$status.' '.$reason,
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: '.strlen($body),
            'Connection: keep-alive',
            '',
            $body,
        ];
        @fwrite($socket, implode("\r\n", $headers));
    }

    private function dropClient(int $id): void
    {
        if (isset($this->clients[$id]) && is_resource($this->clients[$id]['socket'])) {
            @fclose($this->clients[$id]['socket']);
        }
        unset($this->clients[$id], $this->waiters[$id]);
    }

    private function peerIp(string $peer): string
    {
        if (str_starts_with($peer, '[')) {
            $end = strpos($peer, ']');

            return $end === false ? $peer : substr($peer, 1, $end - 1);
        }
        if (substr_count($peer, ':') === 1) {
            return explode(':', $peer)[0];
        }

        return $peer;
    }
}
