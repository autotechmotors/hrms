<?php

return [
    'push_dir' => env('DAHUA_PUSH_DIR', 'D:\\EZAPI_DAHUA_TECHNICAL_MATERIAL\\General_EZAPIDemo_ChnEng_Golang_IS_V1.000.0000000.0.R.20260708(1)\\AutoRegisterAndPush_Person\\bin'),
    'port' => (int) env('DAHUA_PUSH_PORT', 60002),
    'poll_seconds' => (int) env('DAHUA_POLL_SECONDS', 1),
    'host' => env('DAHUA_PUSH_HOST', '192.168.1.68'),
    'register_id' => env('DAHUA_REGISTER_ID', 'test123'),
    'forward_url' => env('DAHUA_FORWARD_URL'),
    'forward_token' => env('DAHUA_FORWARD_TOKEN'),

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
