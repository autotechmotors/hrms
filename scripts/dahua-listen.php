<?php

/**
 * TLS listener for the Dahua terminal. The terminal only completes its handshake
 * with the RSA demo certificate, so this process presents that certificate and
 * forwards the HTTP request to HRMS. The second argument may be host:port or a
 * full URL such as https://hrms.autotechmotors.com.
 *
 * Usage: php scripts/dahua-listen.php [port] [upstream] [cert.pem] [key.pem]
 */

function upstream_target(string $upstream, string $path): array
{
    $upstream = trim($upstream);
    if (! str_contains($upstream, '://')) {
        $upstream = 'http://'.$upstream;
    }
    $parts = parse_url($upstream);
    $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
    if ($host === '') {
        throw new InvalidArgumentException('Upstream must be host:port or an http(s) URL.');
    }
    $scheme = (string) ($parts['scheme'] ?? 'http');
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $origin = $scheme.'://'.$host.($port ? ':'.$port : '');
    $hostHeader = $host.($port ? ':'.$port : '');
    $path = '/'.ltrim($path, '/');

    return [
        'url' => $origin.$path,
        'host' => $hostHeader,
        'https' => $scheme === 'https',
    ];
}

if (PHP_SAPI !== 'cli' || ! isset($argv[0]) || realpath((string) $argv[0]) !== realpath(__FILE__)) {
    return;
}

$port = (int) ($argv[1] ?? 60002);
$upstream = $argv[2] ?? '192.168.1.67:8000';
$cert = $argv[3] ?? 'C:/Users/Computer Garage/Downloads/EZAPI_DAHUA_TECHNICAL_MATERIAL/EZAPI_DAHUA_TECHNICAL_MATERIAL/General_EZAPIDemo_ChnEng_Golang_IS_V1.000.0000000.0.R.20260708(1)/AutoRegisterAndPush_Person/bin/certs/cert.pem';
$key = $argv[4] ?? 'C:/Users/Computer Garage/Downloads/EZAPI_DAHUA_TECHNICAL_MATERIAL/EZAPI_DAHUA_TECHNICAL_MATERIAL/General_EZAPIDemo_ChnEng_Golang_IS_V1.000.0000000.0.R.20260708(1)/AutoRegisterAndPush_Person/bin/certs/key.pem';

$context = stream_context_create([
    'ssl' => [
        'local_cert' => $cert,
        'local_pk' => $key,
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true,
        'security_level' => 0,
    ],
]);

$server = stream_socket_server('tcp://0.0.0.0:'.$port, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "listen failed: $errstr ($errno)\n");
    exit(1);
}
stream_set_blocking($server, false);
$shown = upstream_target($upstream, '/');
fwrite(STDOUT, "Dahua listener on 0.0.0.0:$port -> {$shown['url']}\n");

$clients = [];
$multi = curl_multi_init();
$nextId = 1;

while (true) {
    try {
    $read = [$server];
    $write = [];
    foreach ($clients as $client) {
        if ($client['out'] !== '') {
            $write[] = $client['sock'];
        } elseif ($client['curl'] === null) {
            $read[] = $client['sock'];
        }
    }

    $except = null;
    $selected = @stream_select($read, $write, $except, 0, 200000);
    if ($selected === false) {
        $read = [];
        $write = [];
        foreach ($clients as $id => $client) {
            if (! is_resource($client['sock']) || feof($client['sock'])) {
                drop($clients, $id, $multi);
            }
        }
        continue;
    }

    if (in_array($server, $read, true)) {
        $raw = @stream_socket_accept($server, 0);
        if ($raw !== false) {
            stream_set_blocking($raw, true);
            stream_set_timeout($raw, 8);
            $ok = @stream_socket_enable_crypto($raw, true, STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER);
            stream_set_blocking($raw, false);
            if ($ok === true) {
                $peer = stream_socket_get_name($raw, true) ?: 'unknown';
                fwrite(STDOUT, "connected $peer\n");
                $clients[$nextId] = ['id' => $nextId, 'sock' => $raw, 'peer' => $peer, 'buf' => '', 'out' => '', 'curl' => null];
                $nextId++;
            } else {
                fclose($raw);
            }
        }
    }

    foreach ($clients as $id => $client) {
        if (! isset($clients[$id]) || ! in_array($client['sock'], $read, true) || $client['curl'] !== null) {
            continue;
        }
        $chunk = @fread($client['sock'], 65536);
        if ($chunk === false || $chunk === '') {
            if (feof($client['sock'])) {
                drop($clients, $id, $multi);
            }
            continue;
        }
        $clients[$id]['buf'] .= $chunk;
        $request = take_request($clients[$id]['buf']);
        if ($request === null) {
            continue;
        }
        $handle = forward($upstream, $request);
        $clients[$id]['curl'] = $handle;
        curl_multi_add_handle($multi, $handle);
    }

    do {
        $running = 0;
        $status = curl_multi_exec($multi, $running);
    } while ($status === CURLM_CALL_MULTI_PERFORM);

    while ($info = curl_multi_info_read($multi)) {
        $handle = $info['handle'];
        foreach ($clients as $id => $client) {
            if (! isset($clients[$id]) || $client['curl'] !== $handle) {
                continue;
            }
            $body = (string) curl_multi_getcontent($handle);
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $type = curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: 'application/json; charset=utf-8';
            $path = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            fwrite(STDOUT, $client['peer'].' '.$code.' '.$path."\n");
            $clients[$id]['out'] .= response_bytes($code, $type, $body);
            $clients[$id]['out'] = write_out($clients[$id]['sock'], $clients[$id]['out']);
            $clients[$id]['curl'] = null;
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
            if ($clients[$id]['out'] === '') {
                drop($clients, $id, $multi);
            }
        }
    }

    foreach ($clients as $id => $client) {
        if (! isset($clients[$id]) || $client['out'] === '' || ! in_array($client['sock'], $write, true)) {
            continue;
        }
        $clients[$id]['out'] = write_out($client['sock'], $client['out']);
        if ($clients[$id]['out'] === '' || feof($client['sock'])) {
            drop($clients, $id, $multi);
        }
    }
    } catch (Throwable $error) {
        fwrite(STDERR, 'listener error: '.$error->getMessage()."\n");
    }
}

function take_request(string &$buffer): ?array
{
    $mark = strpos($buffer, "\r\n\r\n");
    if ($mark === false) {
        return null;
    }
    $head = substr($buffer, 0, $mark);
    $length = 0;
    foreach (explode("\r\n", $head) as $index => $line) {
        if ($index > 0 && stripos($line, 'Content-Length:') === 0) {
            $length = (int) trim(substr($line, 15));
        }
    }
    $total = $mark + 4 + $length;
    if (strlen($buffer) < $total) {
        return null;
    }
    $raw = substr($buffer, 0, $total);
    $buffer = substr($buffer, $total);
    $line = strtok($head, "\r\n");
    $parts = preg_split('/\s+/', (string) $line) ?: [];

    return [
        'method' => $parts[0] ?? 'GET',
        'path' => $parts[1] ?? '/',
        'head' => $head,
        'body' => substr($raw, $mark + 4),
    ];
}

function forward(string $upstream, array $request): CurlHandle
{
    $headers = [];
    foreach (explode("\r\n", $request['head']) as $index => $line) {
        if ($index === 0 || ! str_contains($line, ':')) {
            continue;
        }
        $name = strtolower(trim(strtok($line, ':')));
        if (in_array($name, ['host', 'connection', 'content-length', 'accept-encoding'], true)) {
            continue;
        }
        $headers[] = $line;
    }
    $target = upstream_target($upstream, $request['path']);
    $headers[] = 'Host: '.$target['host'];
    $headers[] = 'Connection: close';
    $headers[] = 'Content-Length: '.strlen($request['body']);

    $handle = curl_init($target['url']);
    $options = [
        CURLOPT_CUSTOMREQUEST => $request['method'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => $target['https'],
        CURLOPT_SSL_VERIFYHOST => $target['https'] ? 2 : 0,
    ];
    if ($request['body'] !== '') {
        $options[CURLOPT_POSTFIELDS] = $request['body'];
    }
    curl_setopt_array($handle, $options);

    return $handle;
}

function write_out($socket, string $pending): string
{
    if ($pending === '') {
        return '';
    }
    $sent = @fwrite($socket, $pending);
    if ($sent === false || $sent === 0) {
        return $pending;
    }

    return substr($pending, $sent);
}

function response_bytes(int $code, string $type, string $body): string
{
    $text = $code === 200 ? 'OK' : 'Error';

    return "HTTP/1.1 $code $text\r\nContent-Type: $type\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body;
}

/**
 * @param  array<int, array{sock: resource, curl: ?CurlHandle}>  $clients
 */
function drop(array &$clients, int $id, CurlMultiHandle $multi): void
{
    if (! isset($clients[$id])) {
        return;
    }
    $client = $clients[$id];
    fwrite(STDOUT, "closed ".$client['peer']."\n");
    if ($client['curl'] instanceof CurlHandle) {
        curl_multi_remove_handle($multi, $client['curl']);
        curl_close($client['curl']);
    }
    @fclose($client['sock']);
    unset($clients[$id]);
}
