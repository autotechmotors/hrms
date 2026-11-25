<?php

$inHost = env('HIKVISION_IN_HOST', env('HIKVISION_HOST', '192.168.1.156'));
$inRegister = env('HIKVISION_IN_REGISTER_ID', env('HIKVISION_REGISTER_ID', 'HIK1921681156'));
$inUsername = env('HIKVISION_IN_USERNAME', env('HIKVISION_USERNAME', 'admin'));
$inPassword = env('HIKVISION_IN_PASSWORD', env('HIKVISION_PASSWORD', ''));
$inPort = env('HIKVISION_IN_PORT', env('HIKVISION_PORT'));
$outHost = env('HIKVISION_OUT_HOST', '');
$outPort = env('HIKVISION_OUT_PORT');
$outRegister = env('HIKVISION_OUT_REGISTER_ID', '');
if ($outRegister === '' && is_string($outHost) && $outHost !== '') {
    $outRegister = 'HIK'.preg_replace('/\D+/', '', $outHost);
}

return [
    /*
    | The terminal posts scans itself to /hikvision-events/{DEVICE_FORWARD_TOKEN}.
    | When a port is set, the live server also pulls through that port,
    | so the office PC does not have to stay on.
    | IN and OUT are separate terminals. The top-level keys stay the IN terminal
    | so older calls keep working.
    */
    'pull' => filter_var(env('HIKVISION_IN_PULL', env('HIKVISION_PULL', false)), FILTER_VALIDATE_BOOL)
        || filter_var(env('HIKVISION_OUT_PULL', env('HIKVISION_OUT_ULL', false)), FILTER_VALIDATE_BOOL),
    'public_host' => env('HIKVISION_HOST', ''),
    'host' => $inHost,
    'port' => $inPort !== null && $inPort !== '' ? (int) $inPort : null,
    'register_id' => $inRegister,
    'username' => $inUsername,
    'password' => $inPassword,
    'terminals' => [
        'in' => [
            'pull' => filter_var(env('HIKVISION_IN_PULL', env('HIKVISION_PULL', false)), FILTER_VALIDATE_BOOL),
            'host' => $inHost,
            'port' => $inPort !== null && $inPort !== '' ? (int) $inPort : null,
            'register_id' => $inRegister,
            'username' => $inUsername,
            'password' => $inPassword,
            'punch_role' => 'in',
        ],
        'out' => [
            'pull' => filter_var(env('HIKVISION_OUT_PULL', env('HIKVISION_OUT_ULL', false)), FILTER_VALIDATE_BOOL),
            'host' => $outHost,
            'port' => $outPort !== null && $outPort !== '' ? (int) $outPort : null,
            'register_id' => $outRegister,
            'username' => env('HIKVISION_OUT_USERNAME', 'admin'),
            'password' => env('HIKVISION_OUT_PASSWORD', ''),
            'punch_role' => 'out',
        ],
    ],
];
