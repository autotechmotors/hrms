<?php

namespace App\Services\Devices;

use Illuminate\Support\Facades\Log;

class DeviceLog
{
    public static function debug(string $message, array $context = []): void
    {
        $payload = self::clean($context);
        try {
            Log::channel('devices')->debug($message, $payload);
        } catch (\Throwable) {
        }
    }

    /** Attendance / movement moment for daily review. */
    public static function moment(string $message, array $context = []): void
    {
        $payload = self::clean($context);
        try {
            Log::channel('devices')->info('moment '.$message, $payload);
        } catch (\Throwable) {
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function clean(array $context): array
    {
        $hidden = ['password', 'photodata', 'fingerprintdata', 'featuredata', 'image', 'content'];
        $clean = [];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $hidden, true)) {
                $clean[$key] = '[hidden]';

                continue;
            }
            $clean[$key] = is_array($value) ? self::clean($value) : $value;
        }

        return $clean;
    }
}
