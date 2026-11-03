<?php

return [
    /*
    | Leave pull off on Hostinger. The terminal posts scans itself to
    | /hikvision-events/{DAHUA_FORWARD_TOKEN}. Pull is only a backup on a PC
    | that can see the terminal.
    */
    'pull' => filter_var(env('HIKVISION_PULL', false), FILTER_VALIDATE_BOOL),
    'host' => env('HIKVISION_HOST', '192.168.1.156'),
    'port' => env('HIKVISION_PORT') !== null && env('HIKVISION_PORT') !== '' ? (int) env('HIKVISION_PORT') : null,
    'register_id' => env('HIKVISION_REGISTER_ID', 'HIK1921681156'),
    'username' => env('HIKVISION_USERNAME', 'admin'),
    'password' => env('HIKVISION_PASSWORD', ''),
];
