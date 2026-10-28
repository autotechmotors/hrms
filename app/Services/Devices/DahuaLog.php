<?php

namespace App\Services\Devices;

use Illuminate\Support\Facades\Log;

class DahuaLog
{
    public static function debug(string $message, array $context = []): void
    {
        try {
            Log::channel('dahua')->debug($message, self::clean($context));
        } catch (\Throwable) {
        }
    }

    /** Attendance / movement moment for daily review (storage/logs/dahua-YYYY-MM-DD.log). */
    public static function moment(string $message, array $context = []): void
    {
        try {
            Log::channel('dahua')->info('moment '.$message, self::clean($context));
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
