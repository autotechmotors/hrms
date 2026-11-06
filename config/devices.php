<?php

return [
    /*
    | Shared device hub for Hikvision terminals. DAHUA_FORWARD_* is only a
    | fallback so the live hikvision-events token does not have to be renamed.
    */
    'register_id' => env('DEVICE_REGISTER_ID', env('HIKVISION_REGISTER_ID', 'HIK1921681156')),
    'forward_url' => env('DEVICE_FORWARD_URL', env('DAHUA_FORWARD_URL')),
    'forward_token' => env('DEVICE_FORWARD_TOKEN', env('DAHUA_FORWARD_TOKEN')),
    'forward_dir' => env('DEVICE_FORWARD_DIR')
        ? storage_path(env('DEVICE_FORWARD_DIR'))
        : storage_path('app/device-forward'),
];
