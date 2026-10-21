<?php

namespace App\Support;

use Carbon\Carbon;

class Clock
{
    public static function time(mixed $value): string
    {
        $parsed = self::parse($value);

        return $parsed ? $parsed->format('h:i A') : '—';
    }

    public static function input(mixed $value): string
    {
        $parsed = self::parse($value);

        return $parsed ? $parsed->format('H:i') : '';
    }

    public static function dateTime(mixed $value): string
    {
        $parsed = self::parse($value);

        return $parsed ? $parsed->format('d M Y h:i A') : '—';
    }

    public static function stamp(mixed $value): string
    {
        $parsed = self::parse($value);

        return $parsed ? $parsed->format('d M Y h:i:s A') : '—';
    }

    private static function parse(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone('Asia/Kolkata');
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '—') {
            return null;
        }

        try {
            return Carbon::parse($text, 'Asia/Kolkata')->timezone('Asia/Kolkata');
        } catch (\Throwable) {
            return null;
        }
    }
}
