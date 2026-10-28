<?php

return [
    /*
    | Queue, inbox, and runtime files live inside Laravel storage.
    | No dependency on AutoRegisterAndPush_Person.exe or the EZAPI demo folder.
    */
    'push_dir' => env('DAHUA_PUSH_DIR') ?: storage_path('app/dahua'),
    'cert_dir' => env('DAHUA_CERT_DIR') ?: storage_path('app/dahua/certs'),
    'port' => (int) env('DAHUA_PUSH_PORT', 60002),
    'poll_seconds' => (int) env('DAHUA_POLL_SECONDS', 1),
    'host' => env('DAHUA_PUSH_HOST', '192.168.1.68'),
    'register_id' => env('DAHUA_REGISTER_ID', 'test123'),
    'forward_url' => env('DAHUA_FORWARD_URL'),
    'forward_token' => env('DAHUA_FORWARD_TOKEN'),
    'poll_seconds' => (int) env('DAHUA_POLL_SECONDS', 10),

    /*
    | The same door permission for every employee. 255 is the terminal's
    | all-day plan. The end date is the latest date this terminal accepts.
    */
    'person' => [
        'user_type' => 0,
        'authority' => 2,
        'use_time' => 255,
        'user_status' => 0,
        'doors' => [0],
        'valid_from' => '1970-02-01 00:00:00',
        'valid_to' => '2037-12-31 23:59:59',
        'holiday_plan' => [255],
        'schedule' => [255],
    ],
];
